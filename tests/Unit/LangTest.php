<?php

namespace NetArz\WhmcsAi\Tests\Unit;

use NetArz\WhmcsAi\Lang;
use PHPUnit\Framework\TestCase;

final class LangTest extends TestCase
{
    private const ROOT = __DIR__.'/../../modules/addons/netarz_ai';

    public function test_both_languages_have_exactly_the_same_keys(): void
    {
        $en = array_keys(Lang::strings('english'));
        $fa = array_keys(Lang::strings('farsi'));

        $this->assertSame([], array_values(array_diff($en, $fa)), 'missing in farsi.php');
        $this->assertSame([], array_values(array_diff($fa, $en)), 'missing in english.php');
    }

    public function test_every_key_the_code_asks_for_exists(): void
    {
        $code = '';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::ROOT)) as $file) {
            if ($file->isFile() && preg_match('/\.php$/', $file->getFilename()) && strpos($file->getPathname(), '/lang/') === false) {
                $code .= file_get_contents($file->getPathname());
            }
        }

        preg_match_all("/(?:Lang::get|\\\$t|\\\$L)\\('([a-z_]+)'/", $code, $m);
        preg_match_all("/\\\$(?:switch|field|select|textarea)\\('[a-z_]+', '([a-z_]+)'(?:, (?:\\[[^\\]]*\\], )?'([a-z_]*)')?/", $code, $f);
        $keys = array_unique(array_filter(array_merge($m[1], $f[1], $f[2])));

        $js = file_get_contents(self::ROOT.'/assets/chat.js');
        preg_match_all('/\bS\.([a-z_]+)/', $js, $w);
        foreach ($w[1] as $key) {
            $keys[] = 'w_'.$key;
        }

        $en = Lang::strings('english');
        $missing = array_values(array_filter(array_unique($keys), function ($k) use ($en) {
            return ! isset($en[$k]) && ! in_array($k, ['outcome_', 'mode_', 'event_', 'channel_', 'w_err_'], true);
        }));
        $this->assertSame([], $missing);
    }

    public function test_dynamic_key_families_are_complete(): void
    {
        $en = Lang::strings('english');
        foreach (['off', 'draft', 'auto'] as $mode) {
            $this->assertArrayHasKey('mode_'.$mode, $en);
            $this->assertArrayHasKey('mode_'.$mode.'_hint', $en);
        }
        foreach (['open', 'reply', 'manual'] as $event) {
            $this->assertArrayHasKey('event_'.$event, $en);
        }
        foreach (['chat', 'ticket', 'test'] as $channel) {
            $this->assertArrayHasKey('channel_'.$channel, $en);
        }
        foreach (['replied', 'drafted', 'handoff', 'pending', 'superseded', 'staff_replied', 'paused', 'closed', 'low_balance', 'budget', 'failed', 'skipped'] as $outcome) {
            $this->assertArrayHasKey('outcome_'.$outcome, $en);
        }
        $codes = ['unknown_action', 'forbidden', 'disabled', 'login_required', 'identity_required', 'invalid_email', 'no_thread', 'empty', 'too_long', 'rate_limited', 'duplicate', 'no_department', 'email_required', 'ticket_failed'];
        foreach ($codes as $code) {
            $this->assertArrayHasKey('w_err_'.$code, $en, $code);
        }
    }

    public function test_no_emoji_anywhere_in_the_strings(): void
    {
        foreach (['english', 'farsi'] as $lang) {
            foreach (Lang::strings($lang) as $key => $text) {
                $this->assertDoesNotMatchRegularExpression('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B50}\x{2705}]/u', $text, $lang.':'.$key);
            }
        }
    }

    public function test_persian_copy_follows_the_house_rules(): void
    {
        foreach (Lang::strings('farsi') as $key => $text) {
            // The brand is always written with its diacritics.
            if (preg_match('/نت ?ارز/u', preg_replace('/[\x{064B}-\x{0652}]/u', '', $text)) && strpos($key, '_language') === false) {
                $this->assertStringContainsString('نِت اَرز', $text, $key);
            }
            // Bookish verbs and the informal «تو» never appear.
            $this->assertDoesNotMatchRegularExpression('/(می‌باشد|نمایید|گردید|(?<!ب)فرمایید|کاربر گرامی|بی‌دردسر|فوق‌العاده|\bتو\b)/u', $text, $key);
            // Arabic letters that look Persian.
            $this->assertDoesNotMatchRegularExpression('/[يك]/u', $text, $key);
        }
    }

    public function test_placeholders_match_between_languages(): void
    {
        $en = Lang::strings('english');
        foreach (Lang::strings('farsi') as $key => $text) {
            preg_match_all('/:([a-z]+)/', $en[$key], $a);
            preg_match_all('/:([a-z]+)/', $text, $b);
            sort($a[1]);
            sort($b[1]);
            $this->assertSame($a[1], $b[1], $key);
        }
    }

    public function test_language_names_resolve(): void
    {
        $this->assertSame('farsi', Lang::resolve('Farsi'));
        $this->assertSame('farsi', Lang::resolve('persian'));
        $this->assertSame('english', Lang::resolve('turkish'));
    }
}
