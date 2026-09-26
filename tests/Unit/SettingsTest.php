<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Schema;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\TestCase;
use WHMCS\Database\Capsule;

final class SettingsTest extends TestCase
{
    public function test_activation_creates_every_table_and_is_idempotent(): void
    {
        foreach (Schema::TABLES as $table) {
            $this->assertTrue(Capsule::schema()->hasTable($table), $table);
        }

        Schema::install(); // a second activation must not fail
        $this->assertSame(Schema::VERSION, Settings::get('db_version'));
    }

    public function test_activation_picks_the_first_active_admin_to_post_as(): void
    {
        $this->assertSame('support-ai', Settings::get('ticket_admin'));
    }

    public function test_defaults_are_typed(): void
    {
        $this->assertSame('gpt-4o-mini', Settings::get('model'));
        $this->assertSame(0.3, Settings::get('temperature'));
        $this->assertSame(700, Settings::get('max_tokens'));
        $this->assertTrue(Settings::bool('chat_enabled'));
        $this->assertSame('draft', Settings::get('ticket_mode'), 'a fresh install never answers customers on its own');
        $this->assertSame('https://netarz.ir/api/ai/v1', Settings::baseUrl());
    }

    public function test_api_key_is_stored_encrypted_and_read_back(): void
    {
        Settings::set('api_key', 'sk-ntz-v1-abcdef0123456789abcdef');
        $raw = Capsule::table(Settings::TABLE)->where('key', 'api_key')->value('value');

        $this->assertStringNotContainsString('abcdef0123456789', $raw);
        $this->assertStringStartsWith('whmcs:', $raw);
        Settings::flush();
        $this->assertSame('sk-ntz-v1-abcdef0123456789abcdef', Settings::apiKey());
    }

    public function test_form_rejects_a_malformed_key_and_keeps_the_old_one(): void
    {
        Settings::set('api_key', 'sk-ntz-v1-abcdef0123456789abcdef');
        $errors = Settings::saveForm(['api_key' => 'not-a-key']);

        $this->assertNotEmpty($errors);
        $this->assertSame('sk-ntz-v1-abcdef0123456789abcdef', Settings::apiKey());
    }

    public function test_masked_or_blank_key_in_the_form_keeps_the_saved_key(): void
    {
        Settings::set('api_key', 'sk-ntz-v1-abcdef0123456789abcdef');
        $this->assertSame([], Settings::saveForm(['api_key' => 'sk-ntz-v1-ab••••••••cdef']));
        $this->assertSame([], Settings::saveForm(['api_key' => '']));
        $this->assertSame('sk-ntz-v1-abcdef0123456789abcdef', Settings::apiKey());
    }

    public function test_numbers_are_clamped_and_enums_validated(): void
    {
        $errors = Settings::saveForm([
            'temperature' => '9', 'max_tokens' => '5', 'ticket_min_confidence' => '150', 'chat_burst_ms' => '-4',
            'ticket_mode' => 'yolo', 'language' => 'fa', 'chat_color' => '#123abc',
        ]);

        $this->assertSame([], $errors);
        $this->assertSame(1.5, Settings::get('temperature'));
        $this->assertSame(100, Settings::get('max_tokens'));
        $this->assertSame(100, Settings::get('ticket_min_confidence'));
        $this->assertSame(0, Settings::get('chat_burst_ms'));
        $this->assertSame('draft', Settings::get('ticket_mode'));
        $this->assertSame('fa', Settings::get('language'));
        $this->assertSame('#123abc', Settings::get('chat_color'));
    }

    public function test_bad_colour_and_http_base_url_are_refused(): void
    {
        $this->assertNotEmpty(Settings::saveForm(['chat_color' => 'red']));
        $this->assertNotEmpty(Settings::saveForm(['base_url' => 'http://netarz.ir/api/ai/v1']));
        $this->assertSame('#ffc700', Settings::get('chat_color'));
    }

    public function test_unchecked_switches_turn_off_and_departments_round_trip(): void
    {
        Settings::saveForm(['ticket_departments' => ['2', '3']]); // every switch absent = unchecked

        $this->assertFalse(Settings::bool('chat_enabled'));
        $this->assertFalse(Settings::bool('knowledge_kb'));
        $this->assertSame([2, 3], Settings::ticketDepartments());

        Settings::saveForm(['ticket_departments' => '', 'chat_enabled' => '1']);
        $this->assertSame([], Settings::ticketDepartments());
        $this->assertTrue(Settings::bool('chat_enabled'));
    }

    public function test_the_settings_form_never_touches_internal_state_or_custom_knowledge(): void
    {
        Settings::set('knowledge_custom', 'Our refund window is 7 days.');
        Settings::saveForm(['db_version' => '0.0.1', 'alert_sent_on' => '2000-01-01']);

        $this->assertSame(Schema::VERSION, Settings::get('db_version'));
        $this->assertSame('', Settings::get('alert_sent_on'));
        $this->assertSame('Our refund window is 7 days.', Settings::get('knowledge_custom'));
    }

    public function test_brand_and_agent_names_fall_back_sensibly(): void
    {
        $this->assertSame('Pars Host', Settings::brandName());
        $this->assertSame('Support assistant', Settings::agentName());
        Settings::set('agent_name', 'Nika');
        $this->assertSame('Nika', Settings::agentName());
    }

    public function test_deactivate_keeps_data_and_uninstall_drops_it(): void
    {
        Settings::set('knowledge_custom', 'keep me');
        $this->assertSame('success', netarz_ai_deactivate()['status']);
        $this->assertSame('keep me', Settings::get('knowledge_custom'));

        Schema::uninstall();
        foreach (Schema::TABLES as $table) {
            $this->assertFalse(Capsule::schema()->hasTable($table));
        }
    }

    public function test_module_config_describes_the_module(): void
    {
        $config = netarz_ai_config();
        $this->assertSame('NetArz AI', $config['name']);
        $this->assertSame(Schema::VERSION, $config['version']);
        $this->assertArrayHasKey('fields', $config);
    }
}
