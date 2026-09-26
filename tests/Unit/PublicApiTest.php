<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Clock;
use NetArz\WhmcsAi\PublicApi;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\Support\FakeGateway;
use NetArz\WhmcsAi\Tests\TestCase;

final class PublicApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->connect(['chat_burst_ms' => 0]);
        Clock::$frozen = strtotime('2026-09-26 12:00:00');
    }

    /** Simulate the widget's fetch(). php://input cannot be faked, so the body goes through $_POST — the same path a form-encoded client takes. */
    private function call(string $action, array $body = [], string $token = '', array $server = []): array
    {
        $_SERVER = array_merge($_SERVER, [
            'REQUEST_METHOD' => in_array($action, ['boot', 'poll'], true) ? 'GET' : 'POST',
            'HTTP_X_NETARZ_CHAT' => '1',
            'HTTP_ORIGIN' => 'https://my.parshost.test',
        ], $server);
        if ($token !== '') {
            $_SERVER['HTTP_X_CHAT_TOKEN'] = $token;
        } else {
            unset($_SERVER['HTTP_X_CHAT_TOKEN']);
        }
        $_POST = $body;

        return PublicApi::handle($action);
    }

    public function test_boot_gives_the_widget_its_config_and_strings(): void
    {
        $r = $this->call('boot');

        $this->assertTrue($r['ok']);
        $this->assertSame('Support assistant', $r['config']['agent']);
        $this->assertSame('#ffc700', $r['config']['color']);
        $this->assertSame('Hello, welcome to Pars Host. How can I help you today?', $r['config']['greeting']);
        $this->assertTrue($r['config']['needs_identity']);
        $this->assertFalse($r['config']['rtl']);
        $this->assertSame('Send', $r['config']['strings']['send']);
        $this->assertNull($r['thread']);
        $this->assertArrayNotHasKey('api_key', $r['config']);
        $this->assertStringNotContainsString('sk-ntz', json_encode($r));
    }

    public function test_a_persian_client_area_gets_a_persian_rtl_widget(): void
    {
        $_SESSION['Language'] = 'farsi';
        $r = $this->call('boot');

        $this->assertTrue($r['config']['rtl']);
        $this->assertSame('fa', $r['config']['lang']);
        $this->assertSame('ارسال', $r['config']['strings']['send']);
        $this->assertStringContainsString('Pars Host', $r['config']['greeting']);
    }

    public function test_writes_need_post_and_the_widget_header(): void
    {
        $this->assertSame('forbidden', $this->call('start', [], '', ['REQUEST_METHOD' => 'GET'])['error']);
        $this->assertSame('forbidden', $this->call('start', [], '', ['HTTP_X_NETARZ_CHAT' => ''])['error']);
        $this->assertSame(403, $this->call('send', [], '', ['HTTP_X_NETARZ_CHAT' => ''])['_status']);
    }

    public function test_a_foreign_origin_is_refused(): void
    {
        $r = $this->call('start', ['name' => 'Eve', 'email' => 'eve@evil.test'], '', ['HTTP_ORIGIN' => 'https://evil.test']);
        $this->assertSame('forbidden', $r['error']);
    }

    public function test_guests_must_say_who_they_are_when_the_owner_asks(): void
    {
        $this->assertSame('identity_required', $this->call('start', ['name' => 'A', 'email' => 'nope'])['error']);
        $r = $this->call('start', ['name' => 'Ali', 'email' => 'ali@example.com', 'page' => 'https://my.parshost.test/cart.php']);
        $this->assertTrue($r['ok']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{48}$/', $r['token']);
    }

    public function test_the_whole_conversation_flow(): void
    {
        $token = $this->call('start', ['name' => 'Ali', 'email' => 'ali@example.com'])['token'];

        $sent = $this->call('send', ['body' => 'How much is Starter?'], $token);
        $this->assertTrue($sent['ok']);
        $this->assertSame('visitor', $sent['message']['sender']);

        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Starter is $5 a month.', 92);
        $reply = $this->call('reply', [], $token);
        $this->assertSame('answered', $reply['status']);
        $this->assertSame('Starter is $5 a month.', $reply['messages'][0]['body']);

        $_GET['after'] = (string) $sent['message']['id'];
        $poll = $this->call('poll', [], $token);
        $this->assertCount(1, $poll['messages']);
        $this->assertSame('ai', $poll['state']['mode']);

        $booted = $this->call('boot', [], $token);
        $this->assertCount(2, $booted['thread']['messages'], 'a page reload restores the conversation');
    }

    public function test_someone_elses_token_gets_nothing(): void
    {
        $this->loginClient(1);
        $token = $this->call('start')['token'];
        $this->loginClient(2);

        $this->assertSame('no_thread', $this->call('send', ['body' => 'hi'], $token)['error']);
        $this->assertNull($this->call('boot', [], $token)['thread']);
    }

    public function test_when_guests_are_not_allowed_only_signed_in_clients_chat(): void
    {
        Settings::set('chat_guests', 0);
        $boot = $this->call('boot');
        $this->assertTrue($boot['config']['login_required']);
        $this->assertSame('login_required', $this->call('start', ['name' => 'Ali', 'email' => 'ali@example.com'])['error']);

        $this->loginClient(1);
        $this->assertTrue($this->call('start')['ok']);
    }

    public function test_the_chat_can_be_switched_off(): void
    {
        Settings::set('chat_enabled', 0);
        $this->assertSame('disabled', $this->call('boot')['error']);
    }

    public function test_a_visitor_can_turn_the_chat_into_a_ticket(): void
    {
        $token = $this->call('start', ['name' => 'Ali', 'email' => 'ali@example.com'])['token'];
        $this->call('send', ['body' => 'My invoice is wrong'], $token);

        $r = $this->call('ticket', [], $token);
        $this->assertTrue($r['ok']);
        $this->assertStringContainsString('viewticket.php', $r['url']);
        $this->assertSame('system', end($r['messages'])['sender']);
    }

    public function test_errors_carry_a_translated_message(): void
    {
        $token = $this->call('start', ['name' => 'Ali', 'email' => 'ali@example.com'])['token'];
        Settings::set('chat_max_length', 100);
        $r = $this->call('send', ['body' => str_repeat('x', 200)], $token);
        $this->assertSame('too_long', $r['error']);
        $this->assertSame('This message is too long.', $r['message']);
    }

    public function test_the_response_body_is_unescaped_json_without_the_internal_status(): void
    {
        $this->assertSame('{"ok":false,"error":"x","url":"https://a.test/x","text":"سلام"}', PublicApi::json(['ok' => false, 'error' => 'x', '_status' => 418, 'url' => 'https://a.test/x', 'text' => 'سلام']));
    }

    public function test_one_ip_cannot_open_endless_conversations(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->assertTrue($this->call('start', ['name' => 'Bot '.$i, 'email' => 'bot'.$i.'@example.com'])['ok']);
        }
        $this->assertSame('rate_limited', $this->call('start', ['name' => 'Bot', 'email' => 'bot@example.com'])['error']);
    }
}
