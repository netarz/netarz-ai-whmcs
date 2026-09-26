<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/** Tiny key/value cache on the settings table (keys start with "cache:"). */
final class Cache
{
    public static function get(string $key)
    {
        try {
            $raw = Capsule::table(Settings::TABLE)->where('key', 'cache:'.$key)->value('value');
        } catch (\Throwable $e) {
            return null;
        }
        $data = $raw !== null ? json_decode((string) $raw, true) : null;
        if (! is_array($data) || ! isset($data['exp']) || $data['exp'] < time()) {
            return null;
        }

        return $data['v'];
    }

    public static function put(string $key, $value, int $seconds): void
    {
        $row = ['value' => json_encode(['exp' => time() + $seconds, 'v' => $value], JSON_UNESCAPED_UNICODE)];
        try {
            if (Capsule::table(Settings::TABLE)->where('key', 'cache:'.$key)->exists()) {
                Capsule::table(Settings::TABLE)->where('key', 'cache:'.$key)->update($row);
            } else {
                Capsule::table(Settings::TABLE)->insert(['key' => 'cache:'.$key] + $row);
            }
        } catch (\Throwable $e) {
            // a cache that cannot write is just a slower cache
        }
    }

    public static function forget(string $key): void
    {
        try {
            Capsule::table(Settings::TABLE)->where('key', 'cache:'.$key)->delete();
        } catch (\Throwable $e) {
        }
    }

    public static function remember(string $key, int $seconds, callable $callback)
    {
        $value = self::get($key);
        if ($value !== null) {
            return $value;
        }
        $value = $callback();
        if ($value !== null) {
            self::put($key, $value, $seconds);
        }

        return $value;
    }
}
