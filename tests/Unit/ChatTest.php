<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Chat;
use NetArz\WhmcsAi\Clock;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\Support\FakeGateway;
use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;
use NetArz\WhmcsAi\Tests\TestCase;
use NetArz\WhmcsAi\Usage;
use WHMCS\Database\Capsule;

final class ChatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connect(['chat_burst_ms' => 2000]);
        Clock::$frozen = strtotime('2026-09-26 12:00:00');
    }

    private function guest(): array
    {
        return Chat::start(0, 'Ali', 'ali@example.com', 'https://my.parshost.test/cart.php', '198.51.100.4', 'UA', 'en');
    }

    public function test_only_a_hash_of_the_visitor_token_is_stored(): void
    {
        $s = $this->guest();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $s['token']);
        $row = Capsule::table(Chat::THREADS)->first();
        $this->assertSame(hash('sha256', $s['token']), $row->token_hash);
        $this->assertStringNotContainsString($s['token'], json_encode(Capsule::table(Chat::THREADS)->get()));
    }

    public function test_a_clients_conversation_is_invisible_to_anyone_else(): void
    {
        $s = Chat::start(1, '', '', '', '1.1.1.1', '', 'en');

        $this->assertNotNull(Chat::find($s['token'], 1));
        $this->assertNull(Chat::find($s['token'], 2), 'another client with a stolen token');
        $this->assertNull(Chat::find($s['token'], 0), 'a guest with a stolen token');
        $this->assertNull(Chat::find('not-a-token', 1));
        $this->assertSame('Reza Karimi', $s['thread']->name, 'name and email come from WHMCS, not the form');
        $this->assertSame('reza@example.com', $s['thread']->email);
    }

    public function test_a_guest_who_signs_in_keeps_the_conversation(): void
    {
        $s = $this->guest();
        $thread = Chat::find($s['token'], 2);
        $this->assertSame(2, (int) $thread->client_id);
        $this->assertNull(Chat::find($s['token'], 0), 'from then on it is that client\'s');
    }

    public function test_messages_are_validated(): void
    {
        $s = $this->guest();
        $t = $s['thread'];

        $this->assertSame('empty', Chat::send($t, "  \n ", '1.1.1.1')['error']);
        Settings::set('chat_max_length', 100);
        $this->assertSame('too_long', Chat::send($t, str_repeat('a', 101), '1.1.1.1')['error']);
        $this->assertTrue(Chat::send($t, 'hello', '1.1.1.1')['ok']);
        $this->assertSame('duplicate', Chat::send($t, 'hello', '1.1.1.1')['error'], 'double submit');
        $this->travel(11);
        $this->assertTrue(Chat::send($t, 'hello', '1.1.1.1')['ok'], 'the same words later are a new message');
    }

    public function test_visitors_are_rate_limited_per_hour(): void
    {
        Settings::set('chat_max_per_hour', 3);
        $t = $this->guest()['thread'];
        for ($i = 1; $i <= 3; $i++) {
            $this->assertTrue(Chat::send($t, 'message '.$i, '1.1.1.1')['ok']);
        }
        $this->assertSame('rate_limited', Chat::send($t, 'message 4', '1.1.1.1')['error']);
        $this->travel(3601);
        $this->assertTrue(Chat::send($t, 'message 5', '1.1.1.1')['ok']);
    }

    public function test_the_ai_waits_for_the_visitor_to_stop_typing_then_answers_the_whole_burst_once(): void
    {
        $t = $this->guest()['thread'];
        Chat::send($t, 'Hi', '1.1.1.1');
        Chat::send($t, 'how much is the', '1.1.1.1');
        Chat::send($t, 'Starter plan?', '1.1.1.1');

        $wait = Chat::respond($t);
        $this->assertSame('wait', $wait['status']);
        $this->assertGreaterThan(0, $wait['retry_in']);
        $this->assertSame([], FakeGateway::chatRequests());

        $this->travel(3);
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Starter is $5 a month, or $50 a year.', 95);
        $r = Chat::respond($t);

        $this->assertSame('answered', $r['status']);
        $this->assertSame('Starter is $5 a month, or $50 a year.', $r['messages'][0]['body']);
        $this->assertSame('ai', $r['messages'][0]['sender']);
        $this->assertSame('Support assistant', $r['messages'][0]['name']);

        $req = FakeGateway::chatRequests();
        $this->assertCount(1, $req, 'one answer for three lines');
        $this->assertSame("Hi\nhow much is the\nStarter plan?", end($req[0]['body']['messages'])['content']);

        $this->assertSame('none', Chat::respond($t)['status'], 'nothing left to answer');
    }

    public function test_an_answer_is_thrown_away_if_the_visitor_kept_typing(): void
    {
        $t = $this->guest()['thread'];
        Chat::send($t, 'What is your refund policy', '1.1.1.1');
        $this->travel(3);

        FakeGateway::$chatQueue[] = function () use ($t) {
            // While the model thinks, the visitor adds a line.
            Chat::send($t, 'for domains?', '1.1.1.1');

            return FakeGateway::answer('answer', 'Hosting refunds are 7 days.', 90);
        };

        $this->assertSame('superseded', Chat::respond($t)['status']);
        $this->assertSame(0, Capsule::table(Chat::MESSAGES)->where('sender', 'ai')->count());

        $this->travel(3);
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Domains cannot be refunded.', 90);
        $this->assertSame('answered', Chat::respond($t)['status']);
    }

    public function test_two_simultaneous_reply_requests_call_the_model_once(): void
    {
        $t = $this->guest()['thread'];
        Chat::send($t, 'hello', '1.1.1.1');
        $this->travel(3);

        FakeGateway::$chatQueue[] = function () use ($t) {
            // A second tab asks at the same moment.
            $this->assertSame('busy', Chat::respond(Chat::thread((int) $t->id))['status']);

            return FakeGateway::answer('answer', 'Hi there.', 90);
        };

        $this->assertSame('answered', Chat::respond($t)['status']);
        $this->assertCount(1, FakeGateway::chatRequests());
        $this->assertNull(Chat::thread((int) $t->id)->reply_lock_until, 'the lock is released');
    }

    public function test_handoff_puts_the_visitor_in_the_queue_and_offers_a_ticket_when_nobody_comes(): void
    {
        Settings::set('chat_handoff_wait', 5);
        $t = $this->guest()['thread'];
        Chat::send($t, 'I want my money back, this is the third outage!', '1.1.1.1');
        $this->travel(3);
        FakeGateway::$chatQueue[] = FakeGateway::answer('handoff', 'I am sorry about this. A colleague will reply here shortly.', 30, 'refund request after outages', 'complaint');

        $r = Chat::respond($t);
        $this->assertSame('handoff', $r['status']);
        $thread = Chat::thread((int) $t->id);
        $this->assertSame('waiting', $thread->mode);
        $this->assertSame('refund request after outages', $thread->handoff_reason);
        $this->assertFalse(Chat::state($thread)['offer_ticket']);

        Chat::send($thread, 'hello??', '1.1.1.1');
        $this->assertSame('human', Chat::respond(Chat::thread((int) $t->id))['status'], 'the AI stays out once handed off');

        $this->travel(5 * 60 + 1);
        $this->assertTrue(Chat::state(Chat::thread((int) $t->id))['offer_ticket']);
        $this->assertSame(1, Chat::waitingCount());
    }

    public function test_when_the_ai_cannot_run_the_visitor_still_gets_a_person(): void
    {
        FakeGateway::$balanceUsd = 0.1; // below min_balance
        $t = $this->guest()['thread'];
        Chat::send($t, 'hello', '1.1.1.1');
        $this->travel(3);

        $r = Chat::respond($t);
        $this->assertSame('unavailable', $r['status']);
        $this->assertStringContainsString('colleague', $r['messages'][0]['body']);
        $this->assertSame('waiting', Chat::thread((int) $t->id)->mode);
    }

    public function test_a_goodbye_after_an_answer_stores_nothing(): void
    {
        $t = $this->guest()['thread'];
        Chat::send($t, 'price of Starter?', '1.1.1.1');
        Chat::addMessage((int) $t->id, 'ai', '$5 a month.');
        $this->travel(3);
        Chat::send(Chat::thread((int) $t->id), 'ok thanks bye', '1.1.1.1');
        $this->travel(3);
        FakeGateway::$chatQueue[] = FakeGateway::answer('silent', '', 90);

        $this->assertSame('silent', Chat::respond(Chat::thread((int) $t->id))['status']);
        $this->assertSame(1, Capsule::table(Chat::MESSAGES)->where('sender', 'ai')->count());
    }

    public function test_a_staff_reply_takes_the_conversation_over_and_can_hand_it_back(): void
    {
        $t = $this->guest()['thread'];
        Chat::send($t, 'hello', '1.1.1.1');

        $msg = Chat::adminSend((int) $t->id, 2, 'Hi, this is Sara from Pars Host.');
        $this->assertSame('admin', $msg['sender']);
        $this->assertSame('Sara Ahmadi', $msg['name']);
        $thread = Chat::thread((int) $t->id);
        $this->assertSame('human', $thread->mode);
        $this->assertTrue(Chat::state($thread)['is_human']);
        $this->assertSame('Sara Ahmadi', Chat::state($thread)['agent']);

        Chat::send($thread, 'great, one question', '1.1.1.1');
        $this->travel(3);
        $this->assertSame('human', Chat::respond(Chat::thread((int) $t->id))['status']);
        $this->assertSame([], FakeGateway::chatRequests());

        Chat::setMode((int) $t->id, 'ai');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Sure, ask away.', 90);
        $this->assertSame('answered', Chat::respond(Chat::thread((int) $t->id))['status']);
    }

    public function test_admin_typing_and_seen_ticks_reach_the_visitor(): void
    {
        $t = $this->guest()['thread'];
        $sent = Chat::send($t, 'anyone?', '1.1.1.1')['message'];

        $this->assertFalse(Chat::state(Chat::thread((int) $t->id))['typing']);
        Chat::adminTyping((int) $t->id, 2);
        $this->assertTrue(Chat::state(Chat::thread((int) $t->id))['typing']);
        $this->travel(7);
        $this->assertFalse(Chat::state(Chat::thread((int) $t->id))['typing']);

        Chat::adminThread((int) $t->id, 0, 2);
        $this->assertSame($sent['id'], Chat::state(Chat::thread((int) $t->id))['seen_by_admin']);
        $this->assertSame(0, (int) Chat::thread((int) $t->id)->unread_admin);
    }

    public function test_the_visitor_poll_returns_only_new_messages_and_never_staff_details(): void
    {
        $t = $this->guest()['thread'];
        $first = Chat::send($t, 'one', '1.1.1.1')['message'];
        Chat::addMessage((int) $t->id, 'ai', 'answer', ['confidence' => 88, 'intent' => 'sales']);

        $poll = Chat::poll(Chat::thread((int) $t->id), $first['id']);
        $this->assertCount(1, $poll['messages']);
        $this->assertArrayNotHasKey('meta', $poll['messages'][0], 'confidence and intent are for staff');

        $admin = Chat::adminThread((int) $t->id, 0, 1);
        $this->assertSame(88, $admin['messages'][1]['meta']['confidence']);
    }

    public function test_a_chat_becomes_a_ticket_with_the_transcript(): void
    {
        $s = Chat::start(1, '', '', '', '1.1.1.1', '', 'en');
        Chat::send($s['thread'], 'My site rezashop.test is down', '1.1.1.1');
        Chat::addMessage((int) $s['thread']->id, 'ai', 'Let me get a colleague.');

        $r = Chat::toTicket(Chat::thread((int) $s['thread']->id));
        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('viewticket.php?tid=', $r['url']);

        $open = FakeWhmcs::callsOf('OpenTicket')[0]['params'];
        $this->assertSame(1, $open['clientid']);
        $this->assertSame(1, $open['deptid'], 'first department when none is chosen');
        $this->assertStringContainsString('My site rezashop.test is down', $open['message']);
        $this->assertStringContainsString('Support assistant: Let me get a colleague.', $open['message']);

        $this->assertSame([], Capsule::table('mod_netarz_ai_ticket_jobs')->get()->all(), 'the AI does not answer a ticket it just handed over');
        $this->assertSame($r['ticket_id'], (int) Chat::thread((int) $s['thread']->id)->ticket_id);
        $this->assertTrue(Chat::toTicket(Chat::thread((int) $s['thread']->id))['ok'], 'asking twice is harmless');
        $this->assertCount(1, FakeWhmcs::callsOf('OpenTicket'));
    }

    public function test_a_guest_ticket_needs_an_email(): void
    {
        $s = Chat::start(0, 'Anon', '', '', '1.1.1.1', '', 'en');
        Chat::send($s['thread'], 'hello', '1.1.1.1');
        $this->assertSame('email_required', Chat::toTicket($s['thread'])['error']);

        $g = $this->guest();
        Chat::send($g['thread'], 'hello', '1.1.1.1');
        $r = Chat::toTicket($g['thread']);
        $this->assertTrue($r['ok']);
        $params = end(FakeWhmcs::$calls)['params'];
        $this->assertSame('ali@example.com', FakeWhmcs::callsOf('OpenTicket')[0]['params']['email']);
    }

    public function test_the_chosen_department_is_used_for_chat_tickets(): void
    {
        Settings::set('ticket_departments', '3');
        $g = $this->guest();
        Chat::send($g['thread'], 'billing question', '1.1.1.1');
        Chat::toTicket($g['thread']);
        $this->assertSame(3, FakeWhmcs::callsOf('OpenTicket')[0]['params']['deptid']);
    }

    public function test_the_transcript_merges_bursts_and_skips_system_lines(): void
    {
        $t = $this->guest()['thread'];
        Chat::addMessage((int) $t->id, 'ai', 'Greeting that came before any question');
        Chat::send($t, 'a', '1.1.1.1');
        Chat::send($t, 'b', '1.1.1.1');
        Chat::addMessage((int) $t->id, 'system', 'internal');
        Chat::addMessage((int) $t->id, 'admin', 'reply', [], 2);

        $this->assertSame([
            ['role' => 'user', 'content' => "a\nb"],
            ['role' => 'assistant', 'content' => 'reply'],
        ], Chat::transcript((int) $t->id));
    }

    public function test_old_conversations_and_logs_are_pruned(): void
    {
        Settings::set('retention_days', 30);
        $old = $this->guest()['thread'];
        Chat::send($old, 'old', '1.1.1.1');
        Usage::record(['channel' => 'chat']);
        $this->travel(31 * 86400);
        $new = $this->guest()['thread'];
        Chat::send($new, 'new', '1.1.1.1');

        $this->assertSame(1, Chat::prune());
        $this->assertNull(Chat::thread((int) $old->id));
        $this->assertNotNull(Chat::thread((int) $new->id));
        $this->assertSame(0, Capsule::table(Usage::TABLE)->count());
    }

    public function test_closing_and_reopening(): void
    {
        $t = $this->guest()['thread'];
        Chat::send($t, 'hi', '1.1.1.1');
        Chat::close((int) $t->id);
        $this->assertSame('closed', Chat::thread((int) $t->id)->status);

        Chat::send(Chat::thread((int) $t->id), 'one more thing', '1.1.1.1');
        $this->assertSame('open', Chat::thread((int) $t->id)->status);
        $this->assertSame('ai', Chat::thread((int) $t->id)->mode);
    }

    public function test_with_ai_switched_off_every_chat_waits_for_a_person(): void
    {
        Settings::set('chat_ai_enabled', 0);
        $t = $this->guest()['thread'];
        $this->assertSame('waiting', $t->mode);
        Chat::send($t, 'hi', '1.1.1.1');
        $this->travel(3);
        $this->assertSame('human', Chat::respond($t)['status']);
        $this->assertSame([], FakeGateway::chatRequests());
    }
}
