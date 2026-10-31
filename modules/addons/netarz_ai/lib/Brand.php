<?php

namespace NetArz\WhmcsAi;

/**
 * Which edition this copy of the addon is, and everything that differs between them.
 *
 * One codebase ships as two addons: "NetArz AI" (module netarz_ai, the original
 * release) and "Gisoo AI" (module gisoo_ai). Gisoo is NetArz's AI brand: the
 * account, key, credit and models are the same, so either edition works with
 * either key. build.sh produces the Gisoo edition by renaming the module and
 * rewriting EDITION below; every brand name and link the addon prints comes from
 * here, never from a literal.
 */
final class Brand
{
    /** Rewritten to 'gisoo' by build.sh for the Gisoo edition. */
    public const EDITION = 'netarz';

    public const REPO = 'https://github.com/netarz/netarz-ai-whmcs';

    /** The only address 1.0 knew; an install that saved it made no choice of its own. */
    public const LEGACY_API = 'https://netarz.ir/api/ai/v1';

    /** One service, several addresses: same key, credit, models and paths on each. */
    public const HOSTS = [
        'gisoo' => 'https://gisoo.pro/api/v1',
        'iran' => 'https://ir.gisoo.pro/api/v1',
        'netarz' => 'https://netarz.ir/api/ai/v1',
    ];

    /** @var array<string, array<string, string>> */
    private const EDITIONS = [
        'netarz' => [
            'name_en' => 'NetArz',
            'name_fa' => 'نِت اَرز',
            'site' => 'https://netarz.ir',
            'panel' => '/ai',
            'topup' => '/ai/topup',
            'docs' => '/docs/ai',
            'landing' => '/ai-api/whmcs',
            'key_prefix' => 'sk-ntz-v1-',
            'money_en' => 'Toman',
            'money_fa' => 'تومان',
            'campaign' => 'netarz-ai-whmcs',
            'host' => 'netarz',
        ],
        'gisoo' => [
            'name_en' => 'Gisoo',
            'name_fa' => 'گیسو',
            'site' => 'https://gisoo.pro',
            'panel' => '/app/api',
            'topup' => '/app/credit',
            'docs' => '/docs/whmcs',
            'landing' => '/whmcs',
            'key_prefix' => 'sk-gisoo-v1-',
            'money_en' => 'coins',
            'money_fa' => 'سکه',
            'campaign' => 'gisoo-ai-whmcs',
            'host' => 'gisoo',
        ],
    ];

    public static function edition(): string
    {
        return isset(self::EDITIONS[self::EDITION]) ? self::EDITION : 'netarz';
    }

    public static function isGisoo(): bool
    {
        return self::edition() === 'gisoo';
    }

    public static function get(string $key): string
    {
        return self::EDITIONS[self::edition()][$key] ?? '';
    }

    /** «گیسو» on a Persian screen, "Gisoo" elsewhere. */
    public static function name(?string $language = null): string
    {
        $language = $language ?? Lang::current();

        return self::get(Lang::resolve($language) === 'farsi' ? 'name_fa' : 'name_en');
    }

    /** "Gisoo AI" — the module's own name in WHMCS. */
    public static function title(): string
    {
        return self::get('name_en').' AI';
    }

    /** What the credit is counted in when bought with a bank card: Toman, or coins on Gisoo. */
    public static function money(?string $language = null): string
    {
        $language = $language ?? Lang::current();

        return self::get(Lang::resolve($language) === 'farsi' ? 'money_fa' : 'money_en');
    }

    public static function site(): string
    {
        return self::get('site');
    }

    /** "gisoo.pro/app/api" — the address as people read it. */
    public static function panelText(): string
    {
        return preg_replace('#^https://#', '', self::site()).self::get('panel');
    }

    /**
     * API addresses this edition offers, the default first. The Gisoo edition never
     * names a NetArz address.
     *
     * @return array<string, string>
     */
    public static function hosts(): array
    {
        $hosts = ['gisoo' => self::HOSTS['gisoo'], 'iran' => self::HOSTS['iran']];

        return self::isGisoo() ? $hosts : ['netarz' => self::HOSTS['netarz']] + $hosts;
    }

    /** Language key of an address's label. */
    public static function hostLabel(string $host): string
    {
        return ['gisoo' => 'host_main', 'iran' => 'host_iran', 'netarz' => 'host_legacy'][$host] ?? 'host_custom';
    }

    public static function defaultHost(): string
    {
        return self::get('host');
    }

    public static function defaultApi(): string
    {
        return self::HOSTS[self::defaultHost()];
    }
}
