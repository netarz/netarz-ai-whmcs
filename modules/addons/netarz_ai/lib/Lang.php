<?php

namespace NetArz\WhmcsAi;

/**
 * The module's strings. WHMCS language names ("farsi", "english", "persian")
 * map onto the two files the module ships; anything else falls back to English.
 */
final class Lang
{
    /** @var array<string, array<string, string>> */
    private static $loaded = [];

    /** @var string */
    private static $current = 'english';

    public static function use(string $language): void
    {
        self::$current = self::resolve($language);
    }

    public static function current(): string
    {
        return self::$current;
    }

    public static function isRtl(): bool
    {
        return self::$current === 'farsi';
    }

    public static function code(): string
    {
        return self::$current === 'farsi' ? 'fa' : 'en';
    }

    public static function resolve(string $language): string
    {
        $language = strtolower(trim($language));

        return in_array($language, ['farsi', 'persian', 'fa', 'fa_ir', 'fa-ir'], true) ? 'farsi' : 'english';
    }

    /** @param array<string, string|int|float> $replace */
    public static function get(string $key, array $replace = [], ?string $language = null): string
    {
        $strings = self::strings($language !== null ? self::resolve($language) : self::$current);
        $text = $strings[$key] ?? (self::strings('english')[$key] ?? $key);

        foreach ($replace as $name => $value) {
            $text = str_replace(':'.$name, (string) $value, $text);
        }

        return $text;
    }

    /** @return array<string, string> */
    public static function strings(string $language): array
    {
        if (! isset(self::$loaded[$language])) {
            $file = dirname(__DIR__).'/lang/'.$language.'.php';
            $_ADDONLANG = [];
            if (is_file($file)) {
                include $file;
            }
            self::$loaded[$language] = is_array($_ADDONLANG) ? $_ADDONLANG : [];
        }

        return self::$loaded[$language];
    }

    /** A machine reason (low_confidence, draft_mode, …) in words; the model's own handoff notes pass through. */
    public static function reason(string $reason): string
    {
        $key = 'reason_'.$reason;
        $text = self::get($key);

        return $text !== $key ? $text : $reason;
    }

    /** Keep an LTR fragment ($2.00, sk-ntz-…) intact inside a right-to-left sentence. */
    public static function ltr(string $text): string
    {
        return "\u{2066}".$text."\u{2069}";
    }

    /** Persian digits on a Persian screen. */
    public static function digits(string $text): string
    {
        return self::$current === 'farsi' ? strtr($text, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹', ',' => '٬']) : $text;
    }

    /** Only the keys starting with a prefix, without it — handy for the chat widget. */
    public static function group(string $prefix, ?string $language = null): array
    {
        $out = [];
        foreach (self::strings($language !== null ? self::resolve($language) : self::$current) as $key => $value) {
            if (strpos($key, $prefix) === 0) {
                $out[substr($key, strlen($prefix))] = $value;
            }
        }

        return $out;
    }
}
