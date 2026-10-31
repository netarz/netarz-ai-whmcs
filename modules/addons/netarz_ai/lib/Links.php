<?php

namespace NetArz\WhmcsAi;

/**
 * Links from inside WHMCS back to the brand's site, tagged so it can tell which
 * key sign-ups and top-ups came from installs of this addon.
 */
final class Links
{
    public static function to(string $path, string $content, string $medium = 'admin'): string
    {
        return Brand::site().$path.'?'.http_build_query([
            'utm_source' => 'whmcs-plugin',
            'utm_medium' => $medium,
            'utm_campaign' => Brand::get('campaign'),
            'utm_content' => $content,
        ]);
    }

    public static function panel(string $content, string $medium = 'admin'): string
    {
        return self::to(Brand::get('panel'), $content, $medium);
    }

    public static function topup(string $content, string $medium = 'admin'): string
    {
        return self::to(Brand::get('topup'), $content, $medium);
    }

    public static function docs(string $content): string
    {
        return self::to(Brand::get('docs'), $content);
    }

    public static function landing(string $content, string $medium = 'admin'): string
    {
        return self::to(Brand::get('landing'), $content, $medium);
    }
}
