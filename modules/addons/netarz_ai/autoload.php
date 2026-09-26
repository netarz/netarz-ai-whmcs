<?php

spl_autoload_register(function ($class) {
    $prefix = 'NetArz\\WhmcsAi\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__.'/lib/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    if (is_file($file)) {
        require_once $file;
    }
});
