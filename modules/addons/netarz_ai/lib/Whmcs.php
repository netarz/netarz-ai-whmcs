<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/**
 * Every call the module makes into WHMCS itself goes through here, so the rest
 * of the code reads like plain PHP and the test-suite can stand in for WHMCS.
 */
class Whmcs
{
    /** @var callable|null */
    public static $localApi = null;

    /**
     * localAPI() with the module's admin user.
     *
     * @return array<string, mixed>
     */
    public static function api(string $command, array $params = [], ?string $admin = null): array
    {
        if (self::$localApi) {
            return (array) call_user_func(self::$localApi, $command, $params, $admin);
        }

        if (! function_exists('localAPI')) {
            return ['result' => 'error', 'message' => 'localAPI is not available'];
        }

        $result = $admin !== null && $admin !== '' ? localAPI($command, $params, $admin) : localAPI($command, $params);

        return is_array($result) ? $result : ['result' => 'error', 'message' => 'Unexpected localAPI response'];
    }

    /** A value from tblconfiguration (CompanyName, SystemURL, …). */
    public static function config(string $setting, string $default = ''): string
    {
        try {
            $value = Capsule::table('tblconfiguration')->where('setting', $setting)->value('value');
        } catch (\Throwable $e) {
            return $default;
        }

        return $value === null || $value === '' ? $default : (string) $value;
    }

    public static function systemUrl(): string
    {
        return rtrim(self::config('SystemURL', ''), '/').'/';
    }

    public static function companyName(): string
    {
        return self::config('CompanyName', 'WHMCS');
    }

    /** The logged-in client id in the client area, or 0 for a guest. */
    public static function clientId(): int
    {
        if (class_exists('\WHMCS\Session')) {
            try {
                $uid = (int) \WHMCS\Session::get('uid');
                if ($uid > 0) {
                    return $uid;
                }
            } catch (\Throwable $e) {
                // fall through to the raw session
            }
        }

        return isset($_SESSION['uid']) ? (int) $_SESSION['uid'] : 0;
    }

    /** The logged-in admin id in the admin area, or 0. */
    public static function adminId(): int
    {
        return isset($_SESSION['adminid']) ? (int) $_SESSION['adminid'] : 0;
    }

    /** @return array{id:int, name:string, username:string}|null */
    public static function admin(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        try {
            $row = Capsule::table('tbladmins')->where('id', $id)->first(['id', 'username', 'firstname', 'lastname']);
        } catch (\Throwable $e) {
            return null;
        }

        if (! $row) {
            return null;
        }

        return [
            'id' => (int) $row->id,
            'username' => (string) $row->username,
            'name' => trim($row->firstname.' '.$row->lastname) ?: (string) $row->username,
        ];
    }

    /** @return array<int, string> username => full name, active admins only. */
    public static function admins(): array
    {
        try {
            $rows = Capsule::table('tbladmins')->where('disabled', 0)->orderBy('id')->get(['username', 'firstname', 'lastname']);
        } catch (\Throwable $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row->username] = trim($row->firstname.' '.$row->lastname) ?: (string) $row->username;
        }

        return $out;
    }

    /** @return array<int, string> id => department name. */
    public static function departments(): array
    {
        try {
            $rows = Capsule::table('tblticketdepartments')->orderBy('order')->get(['id', 'name']);
        } catch (\Throwable $e) {
            try {
                $rows = Capsule::table('tblticketdepartments')->orderBy('id')->get(['id', 'name']);
            } catch (\Throwable $e) {
                return [];
            }
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->id] = (string) $row->name;
        }

        return $out;
    }

    /** The client's chosen client-area language, e.g. "farsi" or "english". */
    public static function clientLanguage(): string
    {
        $lang = isset($_SESSION['Language']) ? (string) $_SESSION['Language'] : '';

        return $lang !== '' ? strtolower($lang) : strtolower(self::config('Language', 'english'));
    }

    public static function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        if (function_exists('encrypt')) {
            return 'whmcs:'.encrypt($value);
        }

        return 'b64:'.base64_encode($value);
    }

    public static function decrypt(string $value): string
    {
        if (strpos($value, 'whmcs:') === 0 && function_exists('decrypt')) {
            return (string) decrypt(substr($value, 6));
        }

        if (strpos($value, 'b64:') === 0) {
            return (string) base64_decode(substr($value, 4));
        }

        return $value;
    }

    public static function log(string $message): void
    {
        if (function_exists('logActivity')) {
            logActivity('NetArz AI: '.$message);
        }
    }

    /** Request/response log under Utilities → Logs → Module Log (when enabled there). */
    public static function moduleLog(string $action, $request, $response, $processed = ''): void
    {
        if (function_exists('logModuleCall')) {
            logModuleCall('netarz_ai', $action, $request, $response, $processed, array_filter([Settings::apiKey()]));
        }
    }
}
