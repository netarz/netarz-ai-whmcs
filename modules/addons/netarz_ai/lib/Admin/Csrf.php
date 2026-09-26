<?php

namespace NetArz\WhmcsAi\Admin;

/** A per-session token for the module's own admin forms and AJAX calls. */
final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['netarz_ai_csrf']) || ! is_string($_SESSION['netarz_ai_csrf'])) {
            $_SESSION['netarz_ai_csrf'] = bin2hex(random_bytes(20));
        }

        return $_SESSION['netarz_ai_csrf'];
    }

    public static function check(string $given): bool
    {
        return $given !== '' && ! empty($_SESSION['netarz_ai_csrf']) && hash_equals((string) $_SESSION['netarz_ai_csrf'], $given);
    }
}
