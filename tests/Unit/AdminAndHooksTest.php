<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Admin\Controller;
use NetArz\WhmcsAi\Admin\Csrf;
use NetArz\WhmcsAi\Chat;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\Support\FakeGateway;
use NetArz\WhmcsAi\Tests\Support\FakeWhmcs;
use NetArz\WhmcsAi\Tests\TestCase;
use NetArz\WhmcsAi\Tickets;

final class AdminAndHooksTest extends TestCase
{
    private function controller(): Controller
    {
        return new Controller('addonmodules.php?module=netarz_ai');
    }

    private function post(string $action, array $data, bool $withToken = true): array
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = $data + ($withToken ? ['_ntz' => Csrf::token()] : []);
        $_GET = [];

        return $this->controller()->ajax($action);
    }

    public static function tabs(): array
    {
        $out = [];
        foreach (Controller::TABS as $tab) {
            foreach (['english', 'farsi'] as $lang) {
                $out[$tab.'-'.$lang] = [$tab, $lang];
            }
        }

        return $out;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('tabs')]
    public function test_every_admin_tab_renders_in_both_languages(string $tab, string $language): void
    {
        $this->connect();
        $this->loginAdmin(1);
        Lang::use($language);
        Chat::send(Chat::start(0, 'Ali', 'ali@example.com', '', '1.1.1.1', '', 'en')['thread'], 'hello', '1.1.1.1');
        $_GET = ['tab' => $tab];

        $html = $this->controller()->render();

        $this->assertStringContainsString('class="ntz"', $html);
        $this->assertStringContainsString($language === 'farsi' ? 'dir="rtl"' : 'dir="ltr"', $html);
        $this->assertStringNotContainsString('sk-ntz-v1-testkey0123456789abcdef', $html, 'the key never reaches the page');
        $this->assertDoesNotMatchRegularExpression('/\b(f|s|dash|kn|tp|inbox|tab|mode|outcome|col)_[a-z_]+\b(?![^<]*>)/', strip_tags(preg_replace('#<script.*?</script>#s', '', $html)), 'no untranslated keys on the page');
        if ($tab === 'dashboard') {
            $this->assertStringContainsString('$12.50', $html);
        }
        if ($tab === 'settings') {
            $this->assertStringContainsString('sk-ntz-v1-te••••••••cdef', $html);
            $this->assertStringContainsString('name="_ntz"', $html);
        }
    }

    public function test_settings_are_saved_from_the_form_with_a_valid_token_only(): void
    {
        $this->loginAdmin(1);
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_GET = ['tab' => 'settings'];
        $_POST = ['netarz_settings' => '1', '_ntz' => 'forged', 'agent_name' => 'Nika'];
        $html = $this->controller()->render();
        $this->assertStringContainsString('Your session expired', $html);
        $this->assertSame('', Settings::get('agent_name'));

        $_POST['_ntz'] = Csrf::token();
        $html = $this->controller()->render();
        $this->assertStringContainsString('Settings saved.', $html);
        $this->assertSame('Nika', Settings::get('agent_name'));
    }

    public function test_ajax_writes_without_a_token_are_refused(): void
    {
        $this->loginAdmin(1);
        $t = Chat::start(0, 'Ali', 'ali@example.com', '', '1.1.1.1', '', 'en')['thread'];

        $r = $this->post('chat_send', ['id' => $t->id, 'body' => 'hi'], false);
        $this->assertSame(419, $r['_status']);

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['id' => $t->id, 'body' => 'hi', '_ntz' => Csrf::token()];
        $this->assertSame(419, $this->controller()->ajax('chat_send')['_status'], 'GET never writes');
    }

    public function test_the_inbox_flow_from_the_admin_side(): void
    {
        $this->connect();
        $this->loginAdmin(2);
        $t = Chat::start(0, 'Ali', 'ali@example.com', '', '1.1.1.1', '', 'en')['thread'];
        Chat::send($t, 'Is anyone there?', '1.1.1.1');

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_GET = ['filter' => 'open'];
        $list = $this->controller()->ajax('threads');
        $this->assertSame('Ali', $list['threads'][0]['name']);
        $this->assertSame(1, $list['threads'][0]['unread']);

        $sent = $this->post('chat_send', ['id' => $t->id, 'body' => 'Yes, Sara here.']);
        $this->assertSame('Sara Ahmadi', $sent['message']['name']);
        $this->assertSame('human', Chat::thread((int) $t->id)->mode);

        $this->assertTrue($this->post('chat_ticket', ['id' => $t->id])['ok']);
        $this->assertTrue($this->post('chat_close', ['id' => $t->id])['ok']);
        $this->assertSame('closed', Chat::thread((int) $t->id)->status);
        $this->assertTrue($this->post('chat_delete', ['id' => $t->id])['ok']);
        $this->assertNull(Chat::thread((int) $t->id));
    }

    public function test_the_test_console_runs_the_agent_and_shows_cost_and_sources(): void
    {
        $this->connect();
        $this->loginAdmin(1);
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Use ns1.parshost.test and ns2.parshost.test.', 91);

        $r = $this->post('test', ['question' => 'What are your nameservers?', 'channel' => 'chat']);
        $this->assertTrue($r['ok']);
        $this->assertSame('answer', $r['action']);
        $this->assertSame(91, $r['confidence']);
        $this->assertGreaterThan(0, $r['cost']);
        $this->assertSame('How to point your domain to our nameservers', $r['sources'][0]['title']);

        $k = $this->post('knowledge_preview', ['question' => 'nameservers']);
        $this->assertStringContainsString('ns1.parshost.test', $k['text']);
    }

    public function test_connection_test_and_balance_endpoint(): void
    {
        $this->connect();
        $this->loginAdmin(1);
        $r = $this->post('test_connection', []);
        $this->assertTrue($r['ok']);
        $this->assertSame('$12.50', $r['balance']['usd_display']);

        FakeGateway::$meOverride = ['status' => 401, 'body' => ['error' => ['message' => 'Invalid API key', 'code' => 'invalid_api_key']]];
        $r = $this->post('test_connection', []);
        $this->assertFalse($r['ok']);
        $this->assertSame('Invalid API key', $r['error']);
    }

    public function test_ticket_page_draft_actions(): void
    {
        $this->connect(['ticket_mode' => 'draft']);
        $this->loginAdmin(1);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');

        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Here is a draft.', 88);
        $r = $this->post('ticket_draft', ['ticket_id' => $id]);
        $this->assertSame('Here is a draft.', $r['job']['draft']);

        $this->post('ticket_draft_mark', ['id' => $r['job']['id'], 'status' => 'used']);
        $this->assertNull(Tickets::pendingDraft($id));

        $this->post('ticket_pause', ['ticket_id' => $id, 'paused' => 1]);
        $this->assertSame(1, (int) Tickets::state($id)->ai_paused);
    }

    public function test_the_chat_widget_is_injected_on_client_pages_unless_hidden(): void
    {
        $html = implode('', run_hook('ClientAreaFooterOutput', []));
        $this->assertStringContainsString('chat.js', $html);
        $this->assertStringContainsString('data-endpoint="https://my.parshost.test/index.php?m=netarz_ai"', $html);
        $this->assertStringNotContainsString('sk-ntz', $html);

        Settings::set('chat_hide_paths', "cart.php?a=checkout\n/viewinvoice.php");
        $_SERVER['REQUEST_URI'] = '/viewinvoice.php?id=4';
        $this->assertSame('', implode('', run_hook('ClientAreaFooterOutput', [])));

        $_SERVER['REQUEST_URI'] = '/clientarea.php';
        Settings::set('chat_enabled', 0);
        $this->assertSame('', implode('', run_hook('ClientAreaFooterOutput', [])));
    }

    public function test_admin_pages_get_the_waiting_visitor_alert_script(): void
    {
        $this->assertSame('', implode('', run_hook('AdminAreaFooterOutput', [])), 'nobody signed in');
        $this->loginAdmin(1);
        $this->assertStringContainsString('admin-alerts.js', implode('', run_hook('AdminAreaFooterOutput', [])));
    }

    public function test_the_ticket_page_panel_and_the_dashboard_widget(): void
    {
        $this->connect(['ticket_mode' => 'draft']);
        $this->loginAdmin(2);
        $id = FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Draft <script>alert(1)</script> text', 80);
        Tickets::processDue();

        $panel = implode('', run_hook('AdminAreaViewTicketPage', ['ticketid' => $id]));
        $this->assertStringContainsString('data-ticket-panel', $panel);
        $this->assertStringContainsString('Draft &lt;script&gt;alert(1)&lt;/script&gt; text', $panel, 'model output is escaped');
        $this->assertStringContainsString('dir="rtl"', $panel, 'Sara uses the Persian admin area');

        $widget = run_hook('AdminHomeWidgets', [])[0];
        $this->assertInstanceOf(\WHMCS\Module\AbstractWidget::class, $widget);
        $html = $widget->generateOutput($widget->getData());
        $this->assertStringContainsString('$12.50', $html);
    }

    public function test_cron_processes_due_tickets_and_sends_the_low_credit_alert(): void
    {
        $this->connect(['ticket_mode' => 'auto', 'min_balance' => 5]);
        FakeGateway::$balanceUsd = 7;
        FakeWhmcs::customerOpensTicket(1, 2, 'Q', 'question');
        FakeGateway::$chatQueue[] = FakeGateway::answer('answer', 'Answer from cron', 95);

        run_hook('AfterCronJob', []);

        $this->assertCount(1, FakeWhmcs::callsOf('AddTicketReply'));
        $this->assertCount(1, FakeWhmcs::$adminEmails, '7 USD is under twice the 5 USD floor');
    }

    public function test_the_client_area_page_redirect_does_not_break_json_routes(): void
    {
        $this->assertTrue(function_exists('netarz_ai_clientarea'));
        $this->assertTrue(function_exists('netarz_ai_output'));
        $this->assertTrue(function_exists('netarz_ai_upgrade'));
    }
}
