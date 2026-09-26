<?php

namespace NetArz\WhmcsAi;

/** One place for "now", in the server's timezone like the rest of WHMCS; tests can freeze it. */
final class Clock
{
    /** @var int|null */
    public static $frozen = null;

    public static function time(): int
    {
        return self::$frozen !== null ? self::$frozen : time();
    }

    public static function now(int $offsetSeconds = 0): string
    {
        return date('Y-m-d H:i:s', self::time() + $offsetSeconds);
    }

    public static function today(): string
    {
        return date('Y-m-d 00:00:00', self::time());
    }
}
