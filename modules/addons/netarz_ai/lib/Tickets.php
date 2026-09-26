<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/**
 * AI answers for WHMCS tickets.
 *
 * Every new ticket or customer reply becomes a job. A job is processed right
 * after the page that created it has been sent to the browser (ticket_instant),
 * or by the WHMCS cron — never while the customer waits on the submit button.
 *
 * Modes:
 *  - draft: the answer is written for staff, shown on the ticket page with a
 *           button that drops it into the reply box. Nothing reaches the customer.
 *  - auto:  a confident answer is posted as the configured admin; anything
 *           uncertain, angry, account-changing or off-knowledge becomes a draft
 *           plus an internal note saying why a person is needed.
 */
class Tickets
{
    public const JOBS = 'mod_netarz_ai_ticket_jobs';

    public const STATE = 'mod_netarz_ai_ticket_state';

    public const MAX_ATTEMPTS = 3;

    /** True while the module itself is posting, so its own reply does not count as "a person answered". */
    public static $posting = false;

    /** @var int[] jobs to run once the response has been flushed */
    private static $afterResponse = [];

    /** Hook entry: a customer opened a ticket or replied to one. */
    public static function queue(int $ticketId, int $replyId, string $event, int $deptId): ?int
    {
        // self::$posting: a ticket the module opened itself (a chat turned into a ticket) is already in a person's hands.
        if (self::$posting || $ticketId <= 0 || Settings::get('ticket_mode') === 'off' || Settings::apiKey() === '') {
            return null;
        }

        $departments = Settings::ticketDepartments();
        if ($departments && ! in_array($deptId, $departments, true)) {
            return null;
        }

        $state = self::state($ticketId);
        if ($state && (int) $state->ai_paused === 1) {
            return null;
        }

        $delay = (int) Settings::get('ticket_delay');
        $now = Clock::now();

        try {
            $id = Capsule::table(self::JOBS)->insertGetId([
                'ticket_id' => $ticketId, 'reply_id' => $replyId, 'event' => $event, 'status' => 'pending',
                'due_at' => Clock::now($delay * 60), 'created_at' => $now, 'updated_at' => $now,
            ]);
        } catch (\Throwable $e) {
            return null; // the same event queued twice
        }

        // A newer customer message makes older pending jobs for this ticket pointless.
        Capsule::table(self::JOBS)->where('ticket_id', $ticketId)->where('status', 'pending')->where('id', '<', $id)
            ->update(['status' => 'skipped', 'outcome' => 'superseded', 'updated_at' => $now]);

        if ($delay === 0 && Settings::bool('ticket_instant')) {
            self::runAfterResponse((int) $id);
        }

        return (int) $id;
    }

    /** Hook entry: a staff member answered — the AI steps back on this ticket. */
    public static function staffReplied(int $ticketId): void
    {
        if (self::$posting || $ticketId <= 0) {
            return;
        }

        self::touchState($ticketId, ['human_replied' => 1]);
        $now = Clock::now();
        Capsule::table(self::JOBS)->where('ticket_id', $ticketId)->where('status', 'pending')
            ->update(['status' => 'skipped', 'outcome' => 'staff_replied', 'updated_at' => $now]);
        Capsule::table(self::JOBS)->where('ticket_id', $ticketId)->where('draft_status', 'pending')
            ->update(['draft_status' => 'dismissed', 'updated_at' => $now]);
    }

    /** Cron entry: everything that is due, plus jobs stuck in "processing". */
    public static function processDue(int $limit = 10): array
    {
        $now = Clock::now();
        Capsule::table(self::JOBS)->where('status', 'processing')->where('claimed_at', '<', Clock::now(-600))
            ->where('attempts', '<', self::MAX_ATTEMPTS)->update(['status' => 'pending', 'updated_at' => $now]);

        $ids = Capsule::table(self::JOBS)->where('status', 'pending')->where('due_at', '<=', $now)
            ->orderBy('due_at')->limit($limit)->pluck('id')->all();

        $done = [];
        foreach ($ids as $id) {
            $done[(int) $id] = self::process((int) $id);
        }

        return $done;
    }

    /**
     * Ask for a fresh draft from the ticket page. Always a draft, whatever the mode.
     */
    public static function draftNow(int $ticketId): array
    {
        $now = Clock::now();
        $id = Capsule::table(self::JOBS)->insertGetId([
            'ticket_id' => $ticketId, 'reply_id' => random_int(1, 2000000000), 'event' => 'manual',
            'status' => 'pending', 'due_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $outcome = self::process((int) $id, true);

        return ['outcome' => $outcome, 'job' => self::job((int) $id)];
    }

    /**
     * @return string the outcome: replied | drafted | handoff | skipped:<why> | failed:<why> | retry
     */
    public static function process(int $jobId, bool $forceDraft = false): string
    {
        $claimed = Capsule::table(self::JOBS)->where('id', $jobId)->where('status', 'pending')
            ->update(['status' => 'processing', 'claimed_at' => Clock::now(), 'attempts' => Capsule::raw('attempts + 1'), 'updated_at' => Clock::now()]);
        if ($claimed !== 1) {
            return 'skipped:not_pending';
        }

        try {
            return self::run($jobId, $forceDraft);
        } catch (\Throwable $e) {
            Whmcs::log('ticket job '.$jobId.': '.$e->getMessage());

            return self::finish($jobId, 'failed', 'exception', ['error' => $e->getMessage()]);
        }
    }

    private static function run(int $jobId, bool $forceDraft): string
    {
        $job = self::job($jobId);
        $ticketId = (int) $job->ticket_id;

        $ticket = Whmcs::api('GetTicket', ['ticketid' => $ticketId, 'repliessort' => 'ASC']);
        if (($ticket['result'] ?? '') !== 'success') {
            return self::finish($jobId, 'failed', 'ticket_not_found', ['error' => (string) ($ticket['message'] ?? '')]);
        }

        if (strcasecmp((string) ($ticket['status'] ?? ''), 'Closed') === 0 && ! $forceDraft) {
            return self::finish($jobId, 'skipped', 'closed');
        }

        $state = self::state($ticketId);
        if (! $forceDraft && $state && (int) $state->ai_paused === 1) {
            return self::finish($jobId, 'skipped', 'paused');
        }

        $replies = self::replies($ticket);
        if (! $replies) {
            return self::finish($jobId, 'skipped', 'empty');
        }
        $last = end($replies);
        if (! $forceDraft && $last['role'] === 'assistant') {
            return self::finish($jobId, 'skipped', 'already_answered');
        }

        $transcript = self::transcript($ticket, $replies);
        $reply = Agent::reply('ticket', $transcript, (int) ($ticket['userid'] ?? 0), $ticketId);

        if (! $reply->ran()) {
            $job = self::job($jobId);
            if ($reply->transient && (int) $job->attempts < self::MAX_ATTEMPTS) {
                Capsule::table(self::JOBS)->where('id', $jobId)->update([
                    'status' => 'pending', 'due_at' => Clock::now(300 * (int) $job->attempts), 'error' => $reply->error, 'updated_at' => Clock::now(),
                ]);

                return 'retry';
            }

            return self::finish($jobId, $reply->action === 'unavailable' ? 'skipped' : 'failed', $reply->errorCode, ['error' => $reply->error]);
        }

        $mode = $forceDraft ? 'draft' : (string) Settings::get('ticket_mode');
        $humanOwnsIt = $state && (int) $state->human_replied === 1 && Settings::bool('ticket_skip_human');
        $underCap = ! $state || (int) $state->ai_replies < (int) Settings::get('ticket_max_auto');
        $confident = $reply->confidence >= (int) Settings::get('ticket_min_confidence');
        $admin = trim((string) Settings::get('ticket_admin'));

        $draft = $reply->reply;
        if ($draft === '' && $reply->malformed && $reply->error !== '') {
            $draft = $reply->error; // the model's prose, for staff eyes only
        }

        if ($mode === 'auto' && $reply->answered() && $confident && ! $humanOwnsIt && $underCap && $admin !== '') {
            $message = $reply->reply;
            $signature = trim((string) Settings::get('ticket_signature'));
            if ($signature !== '') {
                $message .= "\n\n".$signature;
            }

            $params = ['ticketid' => $ticketId, 'message' => $message, 'adminusername' => $admin, 'markdown' => true];
            $status = (string) Settings::get('ticket_status_after');
            if ($status !== '') {
                $params['status'] = $status;
            }

            self::$posting = true;
            try {
                $posted = Whmcs::api('AddTicketReply', $params, $admin);
            } finally {
                self::$posting = false;
            }

            if (($posted['result'] ?? '') === 'success') {
                self::touchState($ticketId, ['ai_replies' => Capsule::raw('ai_replies + 1')]);

                return self::finish($jobId, 'done', 'replied', ['draft' => $reply->reply, 'confidence' => $reply->confidence, 'draft_status' => 'used']);
            }

            Whmcs::log('ticket #'.$ticketId.': AddTicketReply failed — '.($posted['message'] ?? 'unknown'));
            // fall through to a draft so the work is not lost
        }

        $why = self::whyNotSent($mode, $reply, $confident, $humanOwnsIt, $underCap, $admin);
        $outcome = $reply->isHandoff() ? 'handoff' : 'drafted';

        if ($reply->isHandoff() && Settings::bool('ticket_note_handoff') && ! $forceDraft) {
            $note = Lang::get('note_handoff', ['reason' => $reply->handoffReason ?: $reply->intent, 'confidence' => $reply->confidence]);
            if ($draft !== '') {
                $note .= "\n\n".Lang::get('note_draft_follows')."\n".$draft;
            }
            self::$posting = true;
            try {
                Whmcs::api('AddTicketNote', ['ticketid' => $ticketId, 'message' => $note, 'markdown' => true], $admin ?: null);
            } finally {
                self::$posting = false;
            }
        }

        return self::finish($jobId, 'done', $outcome, [
            'draft' => $draft, 'confidence' => $reply->confidence,
            'reason' => $reply->isHandoff() ? ($reply->handoffReason ?: $why) : $why,
            'draft_status' => $draft !== '' ? 'pending' : '',
        ]);
    }

    /** The newest draft for a ticket that staff have not used or dismissed. */
    public static function pendingDraft(int $ticketId): ?object
    {
        return Capsule::table(self::JOBS)->where('ticket_id', $ticketId)->where('draft_status', 'pending')
            ->orderByDesc('id')->first() ?: null;
    }

    public static function lastJob(int $ticketId): ?object
    {
        return Capsule::table(self::JOBS)->where('ticket_id', $ticketId)->orderByDesc('id')->first() ?: null;
    }

    public static function markDraft(int $jobId, string $status): void
    {
        if (in_array($status, ['used', 'dismissed'], true)) {
            Capsule::table(self::JOBS)->where('id', $jobId)->update(['draft_status' => $status, 'updated_at' => Clock::now()]);
        }
    }

    public static function setPaused(int $ticketId, bool $paused): void
    {
        self::touchState($ticketId, ['ai_paused' => $paused ? 1 : 0]);
        if ($paused) {
            Capsule::table(self::JOBS)->where('ticket_id', $ticketId)->where('status', 'pending')
                ->update(['status' => 'skipped', 'outcome' => 'paused', 'updated_at' => Clock::now()]);
        }
    }

    public static function state(int $ticketId): ?object
    {
        return Capsule::table(self::STATE)->where('ticket_id', $ticketId)->first() ?: null;
    }

    public static function job(int $id): ?object
    {
        return Capsule::table(self::JOBS)->where('id', $id)->first() ?: null;
    }

    public static function recent(int $limit = 50, string $outcome = ''): array
    {
        $q = Capsule::table(self::JOBS)->orderByDesc('id')->limit($limit);
        if ($outcome !== '') {
            $q->where('outcome', $outcome);
        }

        return $q->get()->all();
    }

    /** @return array<int, array{role:string, content:string, date:string}> */
    public static function replies(array $ticket): array
    {
        $list = $ticket['replies']['reply'] ?? [];
        if (! is_array($list)) {
            return [];
        }

        $out = [];
        foreach ($list as $r) {
            if (! is_array($r)) {
                continue;
            }
            $body = Text::clean(Text::plain((string) ($r['message'] ?? '')));
            $attachments = trim((string) ($r['attachment'] ?? ''));
            if ($attachments !== '') {
                $body .= "\n[".Lang::get('attachment_note', [], 'english').': '.count(array_filter(explode('|', $attachments))).']';
            }
            if ($body === '') {
                continue;
            }
            $out[] = ['role' => trim((string) ($r['admin'] ?? '')) !== '' ? 'assistant' : 'user', 'content' => $body, 'date' => (string) ($r['date'] ?? '')];
        }

        return $out;
    }

    /** @return array<int, array{role:string, content:string}> */
    private static function transcript(array $ticket, array $replies): array
    {
        $turns = [];
        foreach ($replies as $r) {
            $count = count($turns);
            if ($count > 0 && $turns[$count - 1]['role'] === $r['role']) {
                $turns[$count - 1]['content'] .= "\n\n".$r['content'];
            } else {
                $turns[] = ['role' => $r['role'], 'content' => $r['content']];
            }
        }
        while ($turns && $turns[0]['role'] === 'assistant') {
            array_shift($turns);
        }
        if ($turns) {
            $turns[0]['content'] = 'Ticket #'.($ticket['tid'] ?? '').' — '.($ticket['deptname'] ?? '').' — subject: '.($ticket['subject'] ?? '')
                .' — priority: '.($ticket['priority'] ?? '')."\n\n".$turns[0]['content'];
        }

        // Keep the prompt bounded on very long tickets: first message + the latest turns.
        if (count($turns) > 14) {
            $turns = array_merge([$turns[0]], array_slice($turns, -13));
            if ($turns[1]['role'] === 'user') {
                array_splice($turns, 1, 1);
            }
        }

        return $turns;
    }

    private static function whyNotSent(string $mode, AgentReply $reply, bool $confident, bool $humanOwnsIt, bool $underCap, string $admin): string
    {
        if ($mode !== 'auto') {
            return 'draft_mode';
        }
        if ($reply->isHandoff()) {
            return 'handoff';
        }
        if (! $confident) {
            return 'low_confidence';
        }
        if ($humanOwnsIt) {
            return 'staff_on_ticket';
        }
        if (! $underCap) {
            return 'auto_reply_cap';
        }
        if ($admin === '') {
            return 'no_admin_user';
        }

        return 'post_failed';
    }

    private static function finish(int $jobId, string $status, string $outcome, array $extra = []): string
    {
        $row = ['status' => $status, 'outcome' => $outcome, 'updated_at' => Clock::now()];
        if (isset($extra['draft'])) {
            $row['draft'] = $extra['draft'];
        }
        if (isset($extra['confidence'])) {
            $row['confidence'] = (int) $extra['confidence'];
        }
        if (isset($extra['reason'])) {
            $row['reason'] = Text::limit((string) $extra['reason'], 250);
        }
        if (isset($extra['draft_status'])) {
            $row['draft_status'] = $extra['draft_status'];
        }
        if (isset($extra['error'])) {
            $row['error'] = Text::limit((string) $extra['error'], 1000);
        }
        Capsule::table(self::JOBS)->where('id', $jobId)->update($row);

        return $status === 'done' ? $outcome : $status.':'.$outcome;
    }

    private static function touchState(int $ticketId, array $values): void
    {
        $now = Clock::now();
        if (Capsule::table(self::STATE)->where('ticket_id', $ticketId)->exists()) {
            Capsule::table(self::STATE)->where('ticket_id', $ticketId)->update($values + ['updated_at' => $now]);

            return;
        }

        $row = ['ticket_id' => $ticketId, 'ai_paused' => 0, 'human_replied' => 0, 'ai_replies' => 0, 'created_at' => $now, 'updated_at' => $now];
        foreach ($values as $key => $value) {
            $row[$key] = $value instanceof \Illuminate\Database\Query\Expression ? 1 : $value;
        }
        Capsule::table(self::STATE)->insert($row);
    }

    /** Run a job after PHP has handed the page to the browser. */
    private static function runAfterResponse(int $jobId): void
    {
        if (! self::$afterResponse) {
            register_shutdown_function(function () {
                if (function_exists('fastcgi_finish_request')) {
                    @fastcgi_finish_request();
                } elseif (function_exists('litespeed_finish_request')) {
                    @litespeed_finish_request();
                }
                @set_time_limit(120);
                @ignore_user_abort(true);
                foreach (self::$afterResponse as $id) {
                    try {
                        self::process($id);
                    } catch (\Throwable $e) {
                        Whmcs::log('ticket job '.$id.' failed: '.$e->getMessage());
                    }
                }
            });
        }
        self::$afterResponse[] = $jobId;
    }
}
