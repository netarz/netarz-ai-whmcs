<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\Support\FakeGateway;
use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;
use NetArz\WhmcsAi\Tests\TestCase;
use NetArz\WhmcsAi\Tickets;
use WHMCS\Database\Capsule;

final class TicketsTest extends TestCase
{
    private function job(int $ticketId): ?object
    {
        return Capsule::table(Tickets::JOBS)->where('ticket_id', $ticketId)->orderByDesc('id')->first();
    }

    private function replies(int $ticketId): array
    {
        return Capsule::table('tblticketreplies')->where('tid', $ticketId)->orderBy('id')->get()->all();
    }

    public function test_nothing_is_queued_while_the_mode_is_off_or_there_is_no_key(): void
    {
        FakeWhmcs::customerOpensTicket(1, 2, 'Help', 'My site is down');
        $this->assertSame(0, Capsule::table(Tickets::JOBS)->count(), 'no key yet');

        $this->connect(['ticket_mode' => 'off']);
        FakeWhmcs::customerOpensTicket(1, 2, 'Help', 'My site is down');
        $this->assertSame(0, Capsule::table(Tickets::JOBS)->count());
    }

    public function test_only_the_chosen_departments_are_handled(): void
    {
        $this->connect(['ticket_departments' => '1,2']);
        FakeWhmcs::customerOpensTicket(1, 3, 'Invoice', 'Billing question');
        $this->assertSame(0, Capsule::table(Tickets::JOBS)->count());

        $id = FakeWhmcs::customerOpensTicket(1, 2, 'DNS', 'Nameservers?');
        $this->assertSame('pending', $this->job($id)->status);
    }

    public function test_auto_mode_answers_a_confident_ticket_as_the_chosen_admin(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'ticket_signature' => 'Pars Host AI assistant', 'ticket_status_after' => 'Answered']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Nameservers', 'Which nameservers should I use for rezashop.test?');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', "Please set:\nns1.parshost.test\nns2.parshost.test\nChanges take up to 24 hours.", 94);

        $this->assertSame([$this->job($id)->id => 'replied'], Tickets::processDue());

        $reply = FakeWhmcs::callsOf('AddTicketReply')[0];
        $this->assertSame('support-ai', $reply['params']['adminusername']);
        $this->assertSame('Answered', $reply['params']['status']);
        $this->assertStringContainsString('ns1.parshost.test', $reply['params']['message']);
        $this->assertStringEndsWith("\n\nPars Host AI assistant", $reply['params']['message']);
        $replies = $this->replies($id);
        $this->assertSame('Support Assistant', end($replies)->admin);

        $state = Tickets::state($id);
        $this->assertSame(1, (int) $state->ai_replies);
        $this->assertSame(0, (int) $state->human_replied, 'the module\'s own reply is not "a person answered"');

        // The transcript the model saw starts with the ticket header and the customer's words.
        $user = FakeGateway::chatRequests()[0]['body']['messages'][1]['content'];
        $this->assertStringContainsString('Technical — subject: Nameservers', $user);
        $this->assertStringContainsString('Which nameservers should I use', $user);
    }

    public function test_low_confidence_in_auto_mode_stays_a_draft(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'ticket_min_confidence' => 80]);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Migration', 'Can you migrate my site from another host?');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'We can migrate one site for free.', 55);

        Tickets::processDue();

        $this->assertSame([], FakeWhmcs::callsOf('AddTicketReply'));
        $job = $this->job($id);
        $this->assertSame('drafted', $job->outcome);
        $this->assertSame('pending', $job->draft_status);
        $this->assertSame('low_confidence', $job->reason);
        $this->assertSame(55, (int) $job->confidence);
    }

    public function test_draft_mode_never_writes_to_the_customer(): void
    {
        $this->connect(['ticket_mode' => 'draft']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'SSL', 'How do I install SSL?');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Open cPanel → SSL/TLS Status → Run AutoSSL.', 99);

        Tickets::processDue();

        $this->assertSame([], FakeWhmcs::callsOf('AddTicketReply'));
        $this->assertSame('Open cPanel → SSL/TLS Status → Run AutoSSL.', Tickets::pendingDraft($id)->draft);
        $this->assertSame('draft_mode', Tickets::pendingDraft($id)->reason);
    }

    public function test_a_handoff_leaves_an_internal_note_with_the_reason_and_the_draft(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 3, 'Refund', 'I want a refund for my VPS, it was down all week.');
        FakeGateway::$chatQueue[] = FakeGateway::answer('handoff', 'I am sorry about the downtime. A colleague will review your refund.', 40, 'refund request', 'complaint');

        Tickets::processDue();

        $this->assertSame([], FakeWhmcs::callsOf('AddTicketReply'));
        $note = FakeWhmcs::callsOf('AddTicketNote')[0]['params']['message'];
        $this->assertStringContainsString('refund request', $note);
        $this->assertStringContainsString('A colleague will review your refund', $note);
        $this->assertSame('handoff', $this->job($id)->outcome);
    }

    public function test_once_staff_reply_the_ai_steps_back_on_that_ticket(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Email', 'Mail is slow');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'There is a known delay on de2.', 90);
        Tickets::processDue();

        FakeWhmcs::staffReplies($id, 'sara', 'I have moved your mailbox to de3.');
        $this->assertSame(1, (int) Tickets::state($id)->human_replied);

        FakeWhmcs::customerReplies($id, 'Thanks, works now. One more thing: can I get a dedicated IP?');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'A dedicated IP is $3 a month.', 95);
        Tickets::processDue();

        $this->assertCount(2, FakeWhmcs::callsOf('AddTicketReply'), 'the AI reply + Sara, and nothing after Sara');
        $this->assertSame('staff_on_ticket', $this->job($id)->reason);
        $this->assertSame('pending', $this->job($id)->draft_status, 'Sara still gets the draft');
    }

    public function test_a_staff_reply_cancels_a_pending_job(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'ticket_delay' => 10]);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        $this->assertSame([], Tickets::processDue(), 'not due for ten minutes');

        FakeWhmcs::staffReplies($id, 'sara', 'Answered by hand.');
        $this->assertSame('staff_replied', $this->job($id)->outcome);
        $this->travel(700);
        $this->assertSame([], Tickets::processDue());
        $this->assertSame([], FakeGateway::chatRequests());
    }

    public function test_auto_replies_stop_at_the_cap(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'ticket_max_auto' => 1]);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'first question');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'first answer', 90);
        Tickets::processDue();

        FakeWhmcs::customerReplies($id, 'second question');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'second answer', 90);
        Tickets::processDue();

        $this->assertCount(1, FakeWhmcs::callsOf('AddTicketReply'));
        $this->assertSame('auto_reply_cap', $this->job($id)->reason);
    }

    public function test_a_newer_customer_message_supersedes_the_pending_job(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'part one');
        FakeWhmcs::customerReplies($id, 'part two');

        $jobs = Capsule::table(Tickets::JOBS)->where('ticket_id', $id)->orderBy('id')->get();
        $this->assertSame('superseded', $jobs[0]->outcome);
        $this->assertSame('pending', $jobs[1]->status);

        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Both parts answered.', 90);
        Tickets::processDue();
        $this->assertCount(1, FakeGateway::chatRequests(), 'one model call for both messages');
        $this->assertStringContainsString("part one\n\npart two", FakeGateway::chatRequests()[0]['body']['messages'][1]['content']);
    }

    public function test_a_paused_ticket_is_left_alone_until_resumed(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        Tickets::setPaused($id, true);
        $this->assertSame('paused', $this->job($id)->outcome);

        FakeWhmcs::customerReplies($id, 'hello?');
        $this->assertSame('paused', $this->job($id)->outcome, 'no new job while paused');

        Tickets::setPaused($id, false);
        FakeWhmcs::customerReplies($id, 'anyone?');
        $this->assertSame('pending', $this->job($id)->status);
    }

    public function test_closed_tickets_are_skipped(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        Capsule::table('tbltickets')->where('id', $id)->update(['status' => 'Closed']);

        Tickets::processDue();
        $this->assertSame('skipped', $this->job($id)->status);
        $this->assertSame('closed', $this->job($id)->outcome);
    }

    public function test_a_provider_outage_is_retried_and_then_given_up(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');

        FakeGateway::$chatQueue[] = FakeGateway::error(503, 'upstream_error', 'down');
        $this->assertSame('retry', array_values(Tickets::processDue())[0]);
        $this->assertSame('pending', $this->job($id)->status);
        $this->assertSame([], Tickets::processDue(), 'backs off five minutes');

        $this->travel(301);
        FakeGateway::$chatQueue[] = FakeGateway::error(503, 'upstream_error', 'down');
        Tickets::processDue();
        $this->travel(601);
        FakeGateway::$chatQueue[] = FakeGateway::error(503, 'upstream_error', 'down');
        Tickets::processDue();

        $this->assertSame('failed', $this->job($id)->status);
        $this->assertSame(3, (int) $this->job($id)->attempts);
    }

    public function test_low_credit_skips_quietly(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'min_balance' => 5]);
        FakeGateway::$balanceUsd = 1;
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        Tickets::processDue();

        $this->assertSame('skipped', $this->job($id)->status);
        $this->assertSame('low_balance', $this->job($id)->outcome);
        $this->assertSame([], FakeWhmcs::callsOf('AddTicketNote'), 'no note spam on every ticket');
    }

    public function test_without_an_admin_user_auto_mode_falls_back_to_drafts(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'ticket_admin' => '']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'An answer', 95);
        Tickets::processDue();

        $this->assertSame([], FakeWhmcs::callsOf('AddTicketReply'));
        $this->assertSame('no_admin_user', $this->job($id)->reason);
    }

    public function test_staff_can_ask_for_a_draft_on_any_ticket(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'First answer', 95);
        Tickets::processDue();

        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'A fresh take', 70);
        $result = Tickets::draftNow($id);

        $this->assertSame('drafted', $result['outcome']);
        $this->assertSame('A fresh take', $result['job']->draft);
        $this->assertCount(1, FakeWhmcs::callsOf('AddTicketReply'), 'a requested draft is never sent');

        Tickets::markDraft((int) $result['job']->id, 'used');
        $this->assertNull(Tickets::pendingDraft($id));
    }

    public function test_malformed_model_output_becomes_a_staff_only_draft(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        FakeGateway::$chatQueue[] = FakeGateway::completion('Sure! Here is what you should do: restart Apache.');
        Tickets::processDue();

        $this->assertSame([], FakeWhmcs::callsOf('AddTicketReply'));
        $this->assertSame('handoff', $this->job($id)->outcome);
        $this->assertStringContainsString('restart Apache', $this->job($id)->draft);
    }

    public function test_html_and_attachments_are_described_to_the_model_as_text(): void
    {
        $this->connect(['ticket_mode' => 'draft']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Error', '<p>I get <b>500</b> errors</p>');
        Capsule::table('tblticketreplies')->insert(['tid' => $id, 'userid' => 1, 'date' => date('Y-m-d H:i:s'), 'message' => 'screenshot attached', 'attachment' => '123_shot.png|124_log.txt']);
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Check the error log.', 80);
        Tickets::processDue();

        $content = FakeGateway::chatRequests()[0]['body']['messages'][1]['content'];
        $this->assertStringContainsString('I get 500 errors', $content);
        $this->assertStringNotContainsString('<b>', $content);
        $this->assertStringContainsString('[attachments: 2]', $content);
    }

    public function test_a_job_is_processed_only_once_even_if_cron_overlaps(): void
    {
        $this->connect(['ticket_mode' => 'auto']);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        $jobId = (int) $this->job($id)->id;
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'once', 95);

        $this->assertSame('replied', Tickets::process($jobId));
        $this->assertSame('skipped:not_pending', Tickets::process($jobId));
        $this->assertCount(1, FakeWhmcs::callsOf('AddTicketReply'));
    }

    public function test_the_same_ticket_event_cannot_be_queued_twice(): void
    {
        $this->connect();
        $this->assertNotNull(Tickets::queue(99, 5, 'reply', 2));
        $this->assertNull(Tickets::queue(99, 5, 'reply', 2));
    }
}
