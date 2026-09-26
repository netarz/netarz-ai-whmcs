<?php

namespace NetArz\WhmcsAi;

use WHMCS\Database\Capsule;

/**
 * The module's own settings table. WHMCS's addon config screen is a flat list
 * of inputs; this module has forty settings in five groups, so they live here
 * and are edited from the module's own settings tab.
 */
class Settings
{
    public const TABLE = 'mod_netarz_ai_settings';

    public const DEFAULT_BASE_URL = 'https://netarz.ir/api/ai/v1';

    /** @var array<string, string|int|float> */
    public const DEFAULTS = [
        // connection
        'api_key' => '',
        'base_url' => self::DEFAULT_BASE_URL,
        'model' => 'gpt-4o-mini',
        'temperature' => 0.3,
        'max_tokens' => 700,

        // identity
        'agent_name' => '',
        'brand_name' => '',
        'language' => 'auto',          // auto | fa | en
        'tone' => 'friendly',          // friendly | formal
        'instructions' => '',

        // live chat
        'chat_enabled' => 1,
        'chat_ai_enabled' => 1,
        'chat_guests' => 1,
        'chat_guest_email' => 1,
        'chat_position' => 'right',    // right | left (mirrored automatically for RTL)
        'chat_color' => '#ffc700',
        'chat_text_color' => '#14161f',
        'chat_greeting' => '',
        'chat_burst_ms' => 2500,
        'chat_max_per_hour' => 40,
        'chat_max_length' => 2000,
        'chat_history_turns' => 16,
        'chat_handoff_wait' => 5,      // minutes before a waiting visitor is offered a ticket
        'chat_admin_alerts' => 1,
        'chat_hide_paths' => '',       // one path per line, e.g. /cart.php?a=checkout
        'chat_credit' => 1,            // small "AI by NetArz" line under the composer

        // tickets
        'ticket_mode' => 'draft',      // off | draft | auto
        'ticket_departments' => '',    // comma separated ids; empty = every department
        'ticket_admin' => '',          // admin username the replies are posted as
        'ticket_min_confidence' => 75,
        'ticket_max_auto' => 3,
        'ticket_delay' => 0,           // minutes to wait before answering (0 = at once)
        'ticket_instant' => 1,         // answer right after the request instead of waiting for cron
        'ticket_signature' => '',
        'ticket_status_after' => '',   // '' keeps the WHMCS default
        'ticket_note_handoff' => 1,
        'ticket_skip_human' => 1,      // stop auto replies once a person answered the ticket

        // knowledge
        'knowledge_custom' => '',
        'knowledge_kb' => 1,
        'knowledge_announcements' => 1,
        'knowledge_products' => 1,
        'knowledge_domains' => 1,
        'knowledge_network' => 1,
        'knowledge_client' => 1,

        // money
        'daily_budget' => 2.0,         // USD per day, 0 = no cap
        'min_balance' => 0.5,          // USD; below it the agent steps aside
        'balance_alerts' => 1,
        'retention_days' => 180,

        // internal state
        'alert_sent_on' => '',
        'db_version' => '',
    ];

    /** Settings that may never be written from the settings form. */
    public const INTERNAL = ['alert_sent_on', 'db_version'];

    /** @var array<string, string>|null */
    private static $cache = null;

    public static function get(string $key)
    {
        $all = self::all();
        $value = array_key_exists($key, $all) ? $all[$key] : (self::DEFAULTS[$key] ?? null);

        if (! array_key_exists($key, self::DEFAULTS)) {
            return $value;
        }

        $default = self::DEFAULTS[$key];
        if (is_int($default)) {
            return (int) $value;
        }
        if (is_float($default)) {
            return (float) $value;
        }

        return (string) $value;
    }

    public static function bool(string $key): bool
    {
        return (int) self::get($key) === 1;
    }

    public static function apiKey(): string
    {
        return trim(Whmcs::decrypt((string) self::get('api_key')));
    }

    public static function baseUrl(): string
    {
        $url = trim((string) self::get('base_url'));

        return rtrim($url !== '' ? $url : self::DEFAULT_BASE_URL, '/');
    }

    public static function agentName(): string
    {
        $name = trim((string) self::get('agent_name'));

        return $name !== '' ? $name : Lang::get('default_agent_name');
    }

    public static function brandName(): string
    {
        $name = trim((string) self::get('brand_name'));

        return $name !== '' ? $name : Whmcs::companyName();
    }

    /** @return int[] */
    public static function ticketDepartments(): array
    {
        $ids = array_filter(array_map('intval', explode(',', (string) self::get('ticket_departments'))));

        return array_values(array_unique($ids));
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $out = [];
        try {
            foreach (Capsule::table(self::TABLE)->where('key', 'not like', 'cache:%')->get(['key', 'value']) as $row) {
                $out[(string) $row->key] = (string) $row->value;
            }
        } catch (\Throwable $e) {
            // table not created yet (module being activated)
        }

        return self::$cache = $out;
    }

    public static function set(string $key, $value): void
    {
        if ($key === 'api_key') {
            $value = Whmcs::encrypt(trim((string) $value));
        }

        $value = is_bool($value) ? (int) $value : $value;

        $exists = Capsule::table(self::TABLE)->where('key', $key)->exists();
        if ($exists) {
            Capsule::table(self::TABLE)->where('key', $key)->update(['value' => (string) $value]);
        } else {
            Capsule::table(self::TABLE)->insert(['key' => $key, 'value' => (string) $value]);
        }

        if (self::$cache !== null) {
            self::$cache[$key] = (string) $value;
        }
    }

    /**
     * Validate and store the settings form. Unknown keys are ignored, every
     * value is clamped to a sane range, and a blank api_key keeps the old one.
     *
     * @return string[] validation errors (translated)
     */
    public static function saveForm(array $input): array
    {
        $errors = [];
        $clean = [];

        foreach (self::DEFAULTS as $key => $default) {
            if (in_array($key, self::INTERNAL, true)) {
                continue;
            }

            if (is_int($default) && in_array($default, [0, 1], true) && self::isSwitch($key)) {
                $clean[$key] = ! empty($input[$key]) ? 1 : 0;
                continue;
            }

            if (! array_key_exists($key, $input)) {
                continue;
            }

            $value = is_array($input[$key]) ? implode(',', array_map('intval', $input[$key])) : trim((string) $input[$key]);

            if ($key === 'api_key') {
                if ($value === '' || strpos($value, '••') !== false) {
                    continue;
                }
                if (! preg_match('/^sk-[A-Za-z0-9_\-]{16,200}$/', $value)) {
                    $errors[] = Lang::get('err_api_key_format');
                    continue;
                }
            }

            // https only; plain http is accepted for a gateway on this machine (development).
            if ($key === 'base_url' && $value !== '' && ! preg_match('#^(https://[^\s/$.?\#].[^\s]*|http://(127\.0\.0\.1|localhost)(:\d+)?(/[^\s]*)?)$#i', $value)) {
                $errors[] = Lang::get('err_base_url');
                continue;
            }

            if (in_array($key, ['chat_color', 'chat_text_color'], true) && ! preg_match('/^#[0-9a-f]{6}$/i', $value)) {
                $errors[] = Lang::get('err_color');
                continue;
            }

            $clean[$key] = self::clamp($key, $value);
        }

        if (! $errors) {
            foreach ($clean as $key => $value) {
                self::set($key, $value);
            }
        }

        return $errors;
    }

    public static function isSwitch(string $key): bool
    {
        return in_array($key, [
            'chat_enabled', 'chat_ai_enabled', 'chat_guests', 'chat_guest_email', 'chat_admin_alerts', 'chat_credit',
            'ticket_instant', 'ticket_note_handoff', 'ticket_skip_human',
            'knowledge_kb', 'knowledge_announcements', 'knowledge_products', 'knowledge_domains',
            'knowledge_network', 'knowledge_client', 'balance_alerts',
        ], true);
    }

    private static function clamp(string $key, string $value)
    {
        $enums = [
            'language' => ['auto', 'fa', 'en'],
            'tone' => ['friendly', 'formal'],
            'chat_position' => ['right', 'left'],
            'ticket_mode' => ['off', 'draft', 'auto'],
            'ticket_status_after' => ['', 'Answered', 'Open', 'In Progress', 'On Hold'],
        ];
        if (isset($enums[$key])) {
            return in_array($value, $enums[$key], true) ? $value : self::DEFAULTS[$key];
        }

        $ranges = [
            'temperature' => [0, 1.5],
            'max_tokens' => [100, 4000],
            'chat_burst_ms' => [0, 10000],
            'chat_max_per_hour' => [1, 1000],
            'chat_max_length' => [100, 8000],
            'chat_history_turns' => [2, 60],
            'chat_handoff_wait' => [1, 240],
            'ticket_min_confidence' => [0, 100],
            'ticket_max_auto' => [1, 50],
            'ticket_delay' => [0, 1440],
            'daily_budget' => [0, 10000],
            'min_balance' => [0, 10000],
            'retention_days' => [7, 3650],
        ];
        if (isset($ranges[$key])) {
            $number = is_float(self::DEFAULTS[$key]) ? (float) $value : (int) $value;

            return max($ranges[$key][0], min($ranges[$key][1], $number));
        }

        $limits = ['instructions' => 6000, 'knowledge_custom' => 60000, 'ticket_signature' => 500, 'chat_greeting' => 500, 'chat_hide_paths' => 2000];

        return mb_substr($value, 0, $limits[$key] ?? 255);
    }

    public static function flush(): void
    {
        self::$cache = null;
    }
}
