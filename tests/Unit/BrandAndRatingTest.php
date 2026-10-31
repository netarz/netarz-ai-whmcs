<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Brand;
use NetArz\WhmcsAi\Chat;
use NetArz\WhmcsAi\Lang;
use NetArz\WhmcsAi\Links;
use NetArz\WhmcsAi\PublicApi;
use NetArz\WhmcsAi\Settings;
use NetArz\WhmcsAi\Tests\TestCase;
use WHMCS\Database\Capsule;

/** 1.1.0: one codebase, two editions; the API address choice; starter questions and chat ratings. */
final class BrandAndRatingTest extends TestCase
{
    public function test_every_brand_word_comes_from_the_edition(): void
    {
        $name = Brand::name('english');
        $this->assertSame(Brand::isGisoo() ? 'Gi'.'soo' : 'Net'.'Arz', $name);
        $this->assertSame($name.' AI', Brand::title());

        foreach (['english', 'farsi'] as $language) {
            foreach (Lang::strings($language) as $key => $raw) {
                $text = Lang::get($key, [], $language);
                $this->assertStringNotContainsString(':service', $text, $key);
                $this->assertStringNotContainsString(':key_prefix', $text, $key);
                $this->assertStringNotContainsString(Brand::isGisoo() ? 'Net'.'Arz' : 'Gi'.'soo', $text, $key.' names the other edition');
            }
        }

        $this->assertStringContainsString(Brand::name('farsi'), Lang::get('module_title', [], 'farsi'));
        $this->assertStringContainsString(Brand::get('key_prefix'), Lang::get('f_api_key_hint', [], 'english'));
        $this->assertStringContainsString(Brand::money('farsi'), Lang::get('dash_toman', ['amount' => '1'], 'farsi'));
    }

    public function test_links_go_to_the_edition_site_with_utm_tags(): void
    {
        $url = Links::panel('settings-key');

        $this->assertStringStartsWith(Brand::site().Brand::get('panel').'?', $url);
        $this->assertStringContainsString('utm_source=whmcs-plugin', $url);
        $this->assertStringContainsString('utm_campaign='.Brand::get('campaign'), $url);
        $this->assertStringStartsWith(Brand::site().Brand::get('landing'), Links::landing('x'));
    }

    public function test_the_api_address_follows_the_choice(): void
    {
        $this->assertSame(Brand::defaultApi(), Settings::baseUrl(), 'a fresh install uses the edition default');

        $this->assertSame([], Settings::saveForm(['api_host' => 'iran']));
        $this->assertSame('https://ir.gisoo.pro/api/v1', Settings::baseUrl());

        $this->assertSame([], Settings::saveForm(['api_host' => 'gisoo']));
        $this->assertSame('https://gisoo.pro/api/v1', Settings::baseUrl());

        $this->assertSame([], Settings::saveForm(['api_host' => 'custom', 'base_url' => 'https://proxy.example.com/v1/']));
        $this->assertSame('https://proxy.example.com/v1', Settings::baseUrl());

        Settings::saveForm(['api_host' => 'nowhere']);
        $this->assertSame('', Settings::get('api_host'), 'an unknown choice falls back to the default');
    }

    public function test_an_install_from_1_0_keeps_its_address(): void
    {
        Settings::set('base_url', Brand::LEGACY_API);
        $this->assertSame(Brand::defaultApi(), Settings::baseUrl(), 'the old default is not a custom choice');

        Settings::set('base_url', 'http://127.0.0.1:8080/api/ai/v1');
        $this->assertSame('http://127.0.0.1:8080/api/ai/v1', Settings::baseUrl(), 'a typed address survives the upgrade');
    }

    public function test_starter_questions_reach_the_widget(): void
    {
        Settings::set('chat_suggestions', "  How much is hosting?\n\nHow much is hosting?\nDo you sell .ir domains?\nWhere is my order?\nCan I pay in Toman?\nA fifth one is dropped");

        $this->assertSame(['How much is hosting?', 'Do you sell .ir domains?', 'Where is my order?', 'Can I pay in Toman?'], Settings::chatSuggestions());

        $_SERVER['REQUEST_METHOD'] = 'GET';
        $boot = PublicApi::handle('boot');
        $this->assertSame(Settings::chatSuggestions(), $boot['config']['suggestions']);
        $this->assertStringStartsWith(Brand::site().Brand::get('landing'), $boot['config']['credit_url']);
        $this->assertArrayHasKey('rate_question', $boot['config']['strings']);
        $this->assertStringContainsString(Brand::name('english'), $boot['config']['strings']['credit']);
    }

    public function test_a_chat_is_rated_once_and_a_thumbs_down_tells_the_team(): void
    {
        $this->connect();
        $thread = Chat::start(0, 'Ali', 'ali@example.com', '', '203.0.113.5', 'UA', 'fa')['thread'];

        $this->assertFalse(Chat::state($thread)['can_rate'], 'nothing to rate before an answer');
        $this->assertFalse(Chat::rate($thread, -1));

        Chat::addMessage((int) $thread->id, 'ai', 'سلام، بفرمایید.');
        $thread = Chat::thread((int) $thread->id);
        $this->assertTrue(Chat::state($thread)['can_rate']);
        $this->assertFalse(Chat::rate($thread, 5), 'only 1 or -1');

        $this->assertTrue(Chat::rate($thread, -1));
        $thread = Chat::thread((int) $thread->id);
        $this->assertSame(-1, Chat::state($thread)['rating']);
        $this->assertFalse(Chat::state($thread)['can_rate']);
        $this->assertFalse(Chat::rate($thread, 1), 'a second rating is refused');

        $system = Capsule::table(Chat::MESSAGES)->where('thread_id', $thread->id)->where('sender', 'system')->value('body');
        $this->assertSame(Lang::get('chat_rated_down', [], 'farsi'), $system);
        $this->assertSame(1, (int) $thread->unread_admin);
        $this->assertSame(-1, Chat::adminList('all')[0]['rating']);
    }

    public function test_satisfaction_counts_rated_conversations(): void
    {
        foreach ([1, 1, 1, -1, null] as $rating) {
            $thread = Chat::start(0, 'V', 'v@example.com', '', '203.0.113.'.random_int(1, 200), 'UA', 'en')['thread'];
            if ($rating !== null) {
                Capsule::table(Chat::THREADS)->where('id', $thread->id)->update(['rating' => $rating]);
            }
        }

        $s = Chat::satisfaction(date('Y-m-d H:i:s', time() - 86400));
        $this->assertSame(['up' => 3, 'down' => 1, 'rated' => 4, 'total' => 5, 'percent' => 75], $s);
    }

    public function test_rating_can_be_switched_off(): void
    {
        Settings::set('chat_rating', 0);
        $thread = Chat::start(0, 'V', 'v@example.com', '', '203.0.113.7', 'UA', 'en')['thread'];
        Chat::addMessage((int) $thread->id, 'ai', 'Hello');

        $this->assertFalse(Chat::state(Chat::thread((int) $thread->id))['can_rate']);
    }
}
