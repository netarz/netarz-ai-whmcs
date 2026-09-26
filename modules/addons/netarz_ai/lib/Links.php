<?php

namespace NetArz\WhmcsAi;

/**
 * Links from inside WHMCS to netarz.ir, tagged so NetArz can tell which
 * key sign-ups and top-ups came from installs of this addon.
 */
final class Links
{
    public const BASE = 'https://netarz.ir';

    public static function to(string $path, string $content, string $medium = 'admin'): string
    {
        return self::BASE.$path.'?'.http_build_query([
            'utm_source' => 'whmcs-plugin',
            'utm_medium' => $medium,
            'utm_campaign' => 'netarz-ai-whmcs',
            'utm_content' => $content,
        ]);
    }
}
