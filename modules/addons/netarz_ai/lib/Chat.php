<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/**
 * Live chat: threads, messages, the AI turn and the hand-over to a person.
 *
 * A visitor owns a thread by a random token kept in their browser; only its
 * SHA-256 is stored. A thread that belongs to a signed-in client can only be
 * read by that client.
 *
 * The AI does not answer each message as it arrives. The widget asks for a
 * reply once the visitor has stopped typing for a moment (chat_burst_ms), so a
 * question typed over three lines gets one answer, not three.
 */
class Chat
{
    public const THREADS = 'mod_netarz_ai_threads';

    public const MESSAGES = 'mod_netarz_ai_messages';

    /* ------------------------------------------------------------ visitor */

    /** @return array{thread: object, token: string} */
    public static function start(int $clientId, string $name, string $email, string $page, string $ip, string $userAgent, string $language): array
    {
        $token = Text::token(24);
        $now = Clock::now();

        if ($clientId > 0) {
            $details = Whmcs::api('GetClientsDetails', ['clientid' => $clientId]);
            $client = isset($details['client']) && is_array($details['client']) ? $details['client'] : $details;
            $name = trim(($client['firstname'] ?? '').' '.($client['lastname'] ?? '')) ?: $name;
            $email = (string) ($client['email'] ?? $email);
        }

        $id = Capsule::table(self::THREADS)->insertGetId([
            'token_hash' => hash('sha256', $token),
            'client_id' => $clientId > 0 ? $clientId : null,
            'name' => Text::limit(Text::clean($name), 120),
            'email' => Text::limit(Text::clean($email), 190),
            'status' => 'open',
            'mode' => Settings::bool('chat_ai_enabled') ? 'ai' : 'waiting',
            'ai_enabled' => Settings::bool('chat_ai_enabled') ? 1 : 0,
            'page_url' => Text::limit($page, 500),
            'ip' => Text::limit($ip, 45),
            'user_agent' => Text::limit($userAgent, 255),
            'language' => $language === 'fa' ? 'fa' : 'en',
            'created_at' => $now,
            'updated_at' => $now,
            'last_message_at' => $now,
        ]);

        return ['thread' => self::thread($id), 'token' => $token];
    }

    /** New conversations from one IP in the last hour (carrier NAT: the ceiling is generous). */
    public static function tooManyStarts(string $ip, int $max = 30): bool
    {
        if ($ip === '') {
            return false;
        }

        return Capsule::table(self::THREADS)->where('ip', $ip)->where('created_at', '>=', Clock::now(-3600))->count() >= $max;
    }

    /** The thread behind a visitor token, if this visitor may see it. */
    public static function find(string $token, int $clientId): ?object
    {
        if (! preg_match('/^[a-f0-9]{48}$/', $token)) {
            return null;
        }

        $thread = Capsule::table(self::THREADS)->where('token_hash', hash('sha256', $token))->first();
        if (! $thread) {
            return null;
        }

        if ($thread->client_id !== null && (int) $thread->client_id !== $clientId) {
            return null;
        }

        if ($thread->client_id === null && $clientId > 0) {
            // A guest who signs in mid-conversation keeps the conversation.
            Capsule::table(self::THREADS)->where('id', $thread->id)->update(['client_id' => $clientId, 'updated_at' => Clock::now()]);
            $thread->client_id = $clientId;
        }

        return $thread;
    }

    public static function thread(int $id): ?object
    {
        return Capsule::table(self::THREADS)->where('id', $id)->first() ?: null;
    }

    /**
     * Store a visitor message.
     *
     * @return array{ok:bool, error?:string, message?:array}
     */
    public static function send(object $thread, string $body, string $ip): array
    {
        $body = Text::clean($body);
        if ($body === '') {
            return ['ok' => false, 'error' => 'empty'];
        }
        if (mb_strlen($body) > (int) Settings::get('chat_max_length')) {
            return ['ok' => false, 'error' => 'too_long'];
        }
        if (self::overRateLimit($thread, $ip)) {
            return ['ok' => false, 'error' => 'rate_limited'];
        }

        // Repeat guard: the same text twice within ten seconds is a double submit.
        $last = Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->orderByDesc('id')->first(['sender', 'body', 'created_at']);
        if ($last && $last->sender === 'visitor' && $last->body === $body && strtotime((string) $last->created_at) > Clock::time() - 10) {
            return ['ok' => false, 'error' => 'duplicate'];
        }

        $now = Clock::now();
        $id = Capsule::table(self::MESSAGES)->insertGetId([
            'thread_id' => $thread->id, 'sender' => 'visitor', 'body' => $body, 'created_at' => $now,
        ]);

        $update = [
            'last_visitor_at' => $now, 'last_message_at' => $now, 'updated_at' => $now,
            'unread_admin' => Capsule::raw('unread_admin + 1'),
        ];
        if ($thread->status === 'closed') {
            $update['status'] = 'open';
            $update['mode'] = (int) $thread->ai_enabled === 1 && Settings::bool('chat_ai_enabled') ? 'ai' : 'waiting';
        }
        Capsule::table(self::THREADS)->where('id', $thread->id)->update($update);

        return ['ok' => true, 'message' => self::present(self::message($id))];
    }

    /**
     * Let the AI answer whatever the visitor said since the last reply.
     *
     * @return array{status:string, retry_in?:int, messages?:array}
     *   status: none | wait | busy | superseded | answered | handoff | silent | human | unavailable
     */
    public static function respond(object $thread): array
    {
        if (! self::aiMayAnswer($thread)) {
            return ['status' => 'human'];
        }

        $lastVisitor = Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->where('sender', 'visitor')->orderByDesc('id')->first(['id', 'created_at']);
        $lastReply = (int) Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->whereIn('sender', ['ai', 'admin'])->max('id');
        if (! $lastVisitor || (int) $lastVisitor->id < $lastReply) {
            return ['status' => 'none'];
        }

        $burst = (int) Settings::get('chat_burst_ms');
        $quietFor = (Clock::time() - strtotime((string) $lastVisitor->created_at)) * 1000;
        if ($burst > 0 && $quietFor < $burst - 250) {
            return ['status' => 'wait', 'retry_in' => max(300, $burst - $quietFor)];
        }

        if (! self::lock($thread)) {
            return ['status' => 'busy', 'retry_in' => 1500];
        }

        try {
            $reply = Agent::reply('chat', self::transcript((int) $thread->id), (int) $thread->client_id, (int) $thread->id);

            // The visitor kept typing while the model was thinking: throw this
            // answer away and let the widget ask again for the whole burst.
            $newest = (int) Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->where('sender', 'visitor')->max('id');
            if ($newest > (int) $lastVisitor->id) {
                return ['status' => 'superseded', 'retry_in' => 300];
            }

            // A person may have joined while the model was thinking.
            $fresh = self::thread((int) $thread->id);
            if (! $fresh || ! self::aiMayAnswer($fresh)) {
                return ['status' => 'human'];
            }

            if ($reply->answered()) {
                $id = self::addMessage((int) $thread->id, 'ai', $reply->reply, ['confidence' => $reply->confidence, 'intent' => $reply->intent]);

                return ['status' => 'answered', 'messages' => [self::present(self::message($id))]];
            }

            if ($reply->action === 'silent') {
                return ['status' => 'silent'];
            }

            // handoff, unavailable or failed: a person takes it from here.
            $text = $reply->isHandoff() && $reply->reply !== '' ? $reply->reply : Lang::get('chat_handoff_default', [], self::langOf($thread));
            $reason = $reply->isHandoff() ? ($reply->handoffReason ?: $reply->intent) : $reply->errorCode;
            $id = self::addMessage((int) $thread->id, 'ai', $text, ['handoff' => true, 'reason' => $reason]);
            self::handoff((int) $thread->id, $reason);

            return ['status' => $reply->ran() ? 'handoff' : 'unavailable', 'messages' => [self::present(self::message($id))]];
        } finally {
            self::unlock((int) $thread->id);
        }
    }

    /**
     * New messages after a given id, plus the thread's live state.
     */
    public static function poll(object $thread, int $afterId): array
    {
        $messages = Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->where('id', '>', $afterId)->orderBy('id')->limit(100)->get();

        $lastId = $afterId;
        foreach ($messages as $m) {
            $lastId = max($lastId, (int) $m->id);
        }
        if ($lastId > (int) $thread->last_seen_by_visitor) {
            Capsule::table(self::THREADS)->where('id', $thread->id)->update(['last_seen_by_visitor' => $lastId]);
        }

        return [
            'messages' => array_map([self::class, 'present'], $messages->all()),
            'state' => self::state($thread),
        ];
    }

    /** What the widget needs to draw the header and the ticket offer. */
    public static function state(object $thread): array
    {
        $typing = $thread->admin_typing_until !== null && strtotime((string) $thread->admin_typing_until) > Clock::time();
        $aiTyping = $thread->reply_lock_until !== null && strtotime((string) $thread->reply_lock_until) > Clock::time();

        $offerTicket = false;
        if ($thread->mode === 'waiting' && $thread->ticket_id === null && $thread->handoff_at !== null) {
            $waited = Clock::time() - strtotime((string) $thread->handoff_at);
            $adminSince = Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->where('sender', 'admin')->where('created_at', '>=', $thread->handoff_at)->exists();
            $offerTicket = ! $adminSince && $waited >= 60 * (int) Settings::get('chat_handoff_wait');
        }

        $admin = $thread->admin_id ? Whmcs::admin((int) $thread->admin_id) : null;

        return [
            'mode' => (string) $thread->mode,
            'status' => (string) $thread->status,
            'typing' => $typing || $aiTyping,
            'agent' => $thread->mode === 'human' && $admin ? $admin['name'] : Settings::agentName(),
            'is_human' => $thread->mode === 'human',
            'offer_ticket' => $offerTicket,
            'ticket_id' => $thread->ticket_id !== null ? (int) $thread->ticket_id : null,
            'seen_by_admin' => (int) $thread->last_seen_by_admin,
        ];
    }

    /**
     * Turn the conversation into a WHMCS ticket so nothing is lost when no one
     * is online. Returns the ticket id and its client-area link.
     *
     * @return array{ok:bool, ticket_id?:int, tid?:string, url?:string, error?:string}
     */
    public static function toTicket(object $thread, int $deptId = 0): array
    {
        if ($thread->ticket_id !== null) {
            return ['ok' => true, 'ticket_id' => (int) $thread->ticket_id, 'url' => Whmcs::systemUrl().'supporttickets.php'];
        }

        if ($deptId <= 0) {
            $departments = Settings::ticketDepartments() ?: array_keys(Whmcs::departments());
            $deptId = (int) ($departments[0] ?? 0);
        }
        if ($deptId <= 0) {
            return ['ok' => false, 'error' => 'no_department'];
        }

        $lang = self::langOf($thread);
        $lines = [];
        foreach (Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->orderBy('id')->get() as $m) {
            if ($m->sender === 'system') {
                continue;
            }
            $who = $m->sender === 'visitor' ? ($thread->name ?: Lang::get('chat_you', [], $lang)) : ($m->sender === 'admin' ? Lang::get('chat_staff', [], $lang) : Settings::agentName());
            $lines[] = '['.substr((string) $m->created_at, 11, 5).'] '.$who.': '.$m->body;
        }

        $params = [
            'deptid' => $deptId,
            'subject' => Lang::get('chat_ticket_subject', ['name' => $thread->name ?: '#'.$thread->id], $lang),
            'message' => Lang::get('chat_ticket_intro', [], $lang)."\n\n".implode("\n\n", $lines),
            'priority' => 'Medium',
            'markdown' => false,
        ];
        if ($thread->client_id !== null) {
            $params['clientid'] = (int) $thread->client_id;
        } else {
            if ($thread->email === '' || ! filter_var($thread->email, FILTER_VALIDATE_EMAIL)) {
                return ['ok' => false, 'error' => 'email_required'];
            }
            $params['name'] = $thread->name ?: $thread->email;
            $params['email'] = $thread->email;
        }

        Tickets::$posting = true;
        try {
            $result = Whmcs::api('OpenTicket', $params, Settings::get('ticket_admin') ?: null);
        } finally {
            Tickets::$posting = false;
        }

        if (($result['result'] ?? '') !== 'success') {
            Whmcs::log('chat #'.$thread->id.' could not open a ticket: '.($result['message'] ?? 'unknown error'));

            return ['ok' => false, 'error' => 'ticket_failed'];
        }

        $ticketId = (int) ($result['id'] ?? 0);
        $tid = (string) ($result['tid'] ?? '');
        Capsule::table(self::THREADS)->where('id', $thread->id)->update(['ticket_id' => $ticketId, 'updated_at' => Clock::now()]);
        self::addMessage((int) $thread->id, 'system', Lang::get('chat_ticket_created', ['tid' => $tid], $lang));

        $url = Whmcs::systemUrl().'viewticket.php?tid='.rawurlencode($tid).'&c='.rawurlencode((string) ($result['c'] ?? ''));

        return ['ok' => true, 'ticket_id' => $ticketId, 'tid' => $tid, 'url' => $url];
    }

    /* -------------------------------------------------------------- admin */

    public static function adminList(string $filter = 'open', int $limit = 60): array
    {
        $q = Capsule::table(self::THREADS)->orderByDesc('last_message_at')->limit($limit);
        if ($filter === 'open') {
            $q->where('status', 'open');
        } elseif ($filter === 'waiting') {
            $q->where('status', 'open')->where('mode', 'waiting');
        } elseif ($filter === 'closed') {
            $q->where('status', 'closed');
        }

        $threads = $q->get();
        $out = [];
        foreach ($threads as $t) {
            $last = Capsule::table(self::MESSAGES)->where('thread_id', $t->id)->where('sender', '!=', 'system')->orderByDesc('id')->first(['sender', 'body']);
            $out[] = [
                'id' => (int) $t->id,
                'name' => $t->name !== '' ? $t->name : Lang::get('guest').' #'.$t->id,
                'email' => (string) $t->email,
                'client_id' => $t->client_id !== null ? (int) $t->client_id : null,
                'mode' => (string) $t->mode,
                'status' => (string) $t->status,
                'ai_enabled' => (int) $t->ai_enabled === 1,
                'unread' => (int) $t->unread_admin,
                'handoff_reason' => (string) $t->handoff_reason,
                'last' => $last ? Text::limit(str_replace("\n", ' ', (string) $last->body), 90) : '',
                'last_sender' => $last ? (string) $last->sender : '',
                'time' => (string) $t->last_message_at,
                'page' => (string) $t->page_url,
                'ticket_id' => $t->ticket_id !== null ? (int) $t->ticket_id : null,
            ];
        }

        return $out;
    }

    public static function adminThread(int $id, int $afterId, int $adminId): ?array
    {
        $thread = self::thread($id);
        if (! $thread) {
            return null;
        }

        $messages = Capsule::table(self::MESSAGES)->where('thread_id', $id)->where('id', '>', $afterId)->orderBy('id')->limit(300)->get();
        $maxId = (int) Capsule::table(self::MESSAGES)->where('thread_id', $id)->max('id');
        Capsule::table(self::THREADS)->where('id', $id)->update(['unread_admin' => 0, 'last_seen_by_admin' => $maxId]);

        $out = [];
        foreach ($messages as $m) {
            $row = self::present($m, true);
            $out[] = $row;
        }

        return [
            'thread' => [
                'id' => (int) $thread->id,
                'name' => $thread->name !== '' ? $thread->name : Lang::get('guest').' #'.$thread->id,
                'email' => (string) $thread->email,
                'client_id' => $thread->client_id !== null ? (int) $thread->client_id : null,
                'mode' => (string) $thread->mode,
                'status' => (string) $thread->status,
                'ai_enabled' => (int) $thread->ai_enabled === 1,
                'handoff_reason' => (string) $thread->handoff_reason,
                'page' => (string) $thread->page_url,
                'ip' => (string) $thread->ip,
                'ticket_id' => $thread->ticket_id !== null ? (int) $thread->ticket_id : null,
                'created_at' => (string) $thread->created_at,
                'seen_by_visitor' => (int) $thread->last_seen_by_visitor,
                'visitor_active' => $thread->last_visitor_at !== null && strtotime((string) $thread->last_visitor_at) > Clock::time() - 120,
            ],
            'messages' => $out,
        ];
    }

    /** A staff reply. Taking part in a conversation takes it over from the AI. */
    public static function adminSend(int $threadId, int $adminId, string $body): ?array
    {
        $body = Text::clean($body);
        $thread = self::thread($threadId);
        if (! $thread || $body === '') {
            return null;
        }

        $id = self::addMessage($threadId, 'admin', $body, [], $adminId);
        Capsule::table(self::THREADS)->where('id', $threadId)->update([
            'mode' => 'human', 'admin_id' => $adminId, 'status' => 'open', 'unread_admin' => 0,
            'admin_typing_until' => null, 'updated_at' => Clock::now(),
        ]);

        return self::present(self::message($id), true);
    }

    public static function adminTyping(int $threadId, int $adminId): void
    {
        Capsule::table(self::THREADS)->where('id', $threadId)->update(['admin_typing_until' => Clock::now(6), 'admin_id' => $adminId]);
    }

    /** Hand the conversation back to the AI (or take it away from it). */
    public static function setMode(int $threadId, string $mode): void
    {
        $mode = in_array($mode, ['ai', 'human', 'waiting'], true) ? $mode : 'ai';
        $update = ['mode' => $mode, 'updated_at' => Clock::now()];
        if ($mode === 'ai') {
            $update['ai_enabled'] = 1;
            $update['handoff_at'] = null;
            $update['handoff_reason'] = '';
        }
        Capsule::table(self::THREADS)->where('id', $threadId)->update($update);
    }

    public static function setAiEnabled(int $threadId, bool $enabled): void
    {
        $update = ['ai_enabled' => $enabled ? 1 : 0, 'updated_at' => Clock::now()];
        $thread = self::thread($threadId);
        if ($thread && ! $enabled && $thread->mode === 'ai') {
            $update['mode'] = 'waiting';
        }
        if ($thread && $enabled && $thread->mode === 'waiting') {
            $update['mode'] = 'ai';
        }
        Capsule::table(self::THREADS)->where('id', $threadId)->update($update);
    }

    public static function close(int $threadId): void
    {
        Capsule::table(self::THREADS)->where('id', $threadId)->update(['status' => 'closed', 'unread_admin' => 0, 'updated_at' => Clock::now()]);
        $thread = self::thread($threadId);
        if ($thread) {
            self::addMessage($threadId, 'system', Lang::get('chat_closed_note', [], self::langOf($thread)));
        }
    }

    public static function delete(int $threadId): void
    {
        Capsule::table(self::MESSAGES)->where('thread_id', $threadId)->delete();
        Capsule::table(self::THREADS)->where('id', $threadId)->delete();
    }

    /** Conversations a person should look at: handed off and not yet answered. */
    public static function waitingCount(): int
    {
        try {
            return (int) Capsule::table(self::THREADS)->where('status', 'open')
                ->where(function ($q) {
                    $q->where('mode', 'waiting')->orWhere(function ($q) {
                        $q->where('mode', 'human')->where('unread_admin', '>', 0);
                    });
                })->count();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /** Unanswered visitors — for the admin toast. */
    public static function alerts(int $sinceId): array
    {
        $rows = Capsule::table(self::THREADS)->where('status', 'open')->where('unread_admin', '>', 0)
            ->whereIn('mode', ['waiting', 'human'])->orderByDesc('last_message_at')->limit(5)->get(['id', 'name', 'mode', 'handoff_reason', 'unread_admin']);

        $maxMessage = (int) Capsule::table(self::MESSAGES)->where('sender', 'visitor')->max('id');

        return [
            'count' => self::waitingCount(),
            'last_message_id' => $maxMessage,
            'fresh' => $maxMessage > $sinceId,
            'threads' => array_map(function ($t) {
                return ['id' => (int) $t->id, 'name' => $t->name !== '' ? $t->name : Lang::get('guest').' #'.$t->id, 'reason' => (string) $t->handoff_reason, 'unread' => (int) $t->unread_admin];
            }, $rows->all()),
        ];
    }

    /** Remove conversations older than the retention window. Called from cron. */
    public static function prune(): int
    {
        $before = Clock::now(-86400 * (int) Settings::get('retention_days'));
        $ids = Capsule::table(self::THREADS)->where('last_message_at', '<', $before)->pluck('id')->all();
        foreach (array_chunk($ids, 200) as $chunk) {
            Capsule::table(self::MESSAGES)->whereIn('thread_id', $chunk)->delete();
            Capsule::table(self::THREADS)->whereIn('id', $chunk)->delete();
        }
        Capsule::table(Usage::TABLE)->where('created_at', '<', $before)->delete();

        return count($ids);
    }

    /* ----------------------------------------------------------- internals */

    public static function aiMayAnswer(object $thread): bool
    {
        return Settings::bool('chat_ai_enabled') && (int) $thread->ai_enabled === 1 && $thread->mode === 'ai' && $thread->status === 'open';
    }

    /** @return array<int, array{role:string, content:string}> */
    public static function transcript(int $threadId): array
    {
        $limit = (int) Settings::get('chat_history_turns') * 2;
        $rows = Capsule::table(self::MESSAGES)->where('thread_id', $threadId)->where('sender', '!=', 'system')->orderByDesc('id')->limit($limit)->get(['sender', 'body']);

        $turns = [];
        foreach (array_reverse($rows->all()) as $m) {
            $role = $m->sender === 'visitor' ? 'user' : 'assistant';
            $count = count($turns);
            if ($count > 0 && $turns[$count - 1]['role'] === $role) {
                // A burst of lines is one turn.
                $turns[$count - 1]['content'] .= "\n".$m->body;
            } else {
                $turns[] = ['role' => $role, 'content' => (string) $m->body];
            }
        }
        while ($turns && $turns[0]['role'] === 'assistant') {
            array_shift($turns);
        }

        return $turns;
    }

    public static function addMessage(int $threadId, string $sender, string $body, array $meta = [], ?int $adminId = null): int
    {
        $now = Clock::now();
        $id = Capsule::table(self::MESSAGES)->insertGetId([
            'thread_id' => $threadId, 'sender' => $sender, 'admin_id' => $adminId, 'body' => $body,
            'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'created_at' => $now,
        ]);
        if ($sender !== 'system') {
            Capsule::table(self::THREADS)->where('id', $threadId)->update(['last_message_at' => $now, 'updated_at' => $now]);
        }

        return (int) $id;
    }

    private static function message(int $id): object
    {
        return Capsule::table(self::MESSAGES)->where('id', $id)->first();
    }

    /** The public shape of a message. Staff-only details stay out unless $admin. */
    public static function present(object $m, bool $admin = false): array
    {
        $meta = $m->meta ? (json_decode((string) $m->meta, true) ?: []) : [];
        $name = '';
        if ($m->sender === 'ai') {
            $name = Settings::agentName();
        } elseif ($m->sender === 'admin') {
            $a = Whmcs::admin((int) $m->admin_id);
            $name = $a ? $a['name'] : Lang::get('chat_staff');
        }

        $row = [
            'id' => (int) $m->id,
            'sender' => (string) $m->sender,
            'name' => $name,
            'body' => (string) $m->body,
            'time' => substr((string) $m->created_at, 11, 5),
            'at' => (string) $m->created_at,
        ];
        if ($admin) {
            $row['meta'] = $meta;
        }

        return $row;
    }

    private static function overRateLimit(object $thread, string $ip): bool
    {
        $max = (int) Settings::get('chat_max_per_hour');
        $since = Clock::now(-3600);

        $inThread = Capsule::table(self::MESSAGES)->where('thread_id', $thread->id)->where('sender', 'visitor')->where('created_at', '>=', $since)->count();
        if ($inThread >= $max) {
            return true;
        }

        if ($ip === '') {
            return false;
        }

        // Many users can share one IP (carrier NAT), so the per-IP ceiling is generous.
        $fromIp = Capsule::table(self::MESSAGES.' as m')->join(self::THREADS.' as t', 't.id', '=', 'm.thread_id')
            ->where('t.ip', $ip)->where('m.sender', 'visitor')->where('m.created_at', '>=', $since)->count();

        return $fromIp >= $max * 5;
    }

    private static function lock(object $thread): bool
    {
        $now = Clock::now();
        $affected = Capsule::table(self::THREADS)->where('id', $thread->id)
            ->where(function ($q) use ($now) {
                $q->whereNull('reply_lock_until')->orWhere('reply_lock_until', '<', $now);
            })
            ->update(['reply_lock_until' => Clock::now(90)]);

        return $affected === 1;
    }

    private static function unlock(int $threadId): void
    {
        Capsule::table(self::THREADS)->where('id', $threadId)->update(['reply_lock_until' => null]);
    }

    private static function handoff(int $threadId, string $reason): void
    {
        Capsule::table(self::THREADS)->where('id', $threadId)->update([
            'mode' => 'waiting', 'handoff_at' => Clock::now(), 'handoff_reason' => Text::limit($reason, 250), 'updated_at' => Clock::now(),
        ]);
    }

    public static function langOf(object $thread): string
    {
        return $thread->language === 'fa' ? 'farsi' : 'english';
    }
}
