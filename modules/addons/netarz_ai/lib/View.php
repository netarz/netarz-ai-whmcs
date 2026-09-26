<?php

namespace NetArz\WhmcsAi;

/** Plain PHP templates from views/, with $e() for escaping. */
final class View
{
    public static function render(string $name, array $data = []): string
    {
        $file = dirname(__DIR__).'/views/'.$name.'.php';
        if (! is_file($file)) {
            return '';
        }

        $e = function ($value) {
            return Text::e($value);
        };
        $t = function (string $key, array $replace = []) {
            return Text::e(Lang::get($key, $replace));
        };
        $icon = function (string $name, int $size = 18, string $class = '') {
            return Icons::svg($name, $size, $class);
        };
        $view = function (string $partial, array $more = []) use ($data) {
            return self::render($partial, $more + $data);
        };

        extract($data, EXTR_SKIP);
        ob_start();
        include $file;

        return (string) ob_get_clean();
    }

    public static function asset(string $file): string
    {
        $path = dirname(__DIR__).'/assets/'.$file;
        $version = is_file($path) ? substr(md5((string) filemtime($path).Schema::VERSION), 0, 8) : Schema::VERSION;

        return Whmcs::systemUrl().'modules/addons/netarz_ai/assets/'.$file.'?v='.$version;
    }
}
