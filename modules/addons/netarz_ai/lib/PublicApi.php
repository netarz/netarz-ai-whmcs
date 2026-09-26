<?php

namespace NetArz\WhmcsAi;

/**
 * JSON endpoints for the chat widget, served through WHMCS itself at
 * index.php?m=netarz_ai&na=<action> so the WHMCS session (and therefore the
 * signed-in client) is available and no file under /modules is called directly.
 *
 * Writes need the X-NetArz-Chat header: a cross-site form cannot send a custom
 * header, and a cross-site script cannot send one without a CORS preflight
 * that this endpoint never approves.
 */
class PublicApi
{
    public const ACTIONS = ['boot', 'start', 'send', 'reply', 'poll', 'ticket', 'end'];

    public static function handle(string $action): array
    {
        if (! in_array($action, self::ACTIONS, true)) {
            return self::error('unknown_action', 404);
        }

        $isWrite = $action !== 'boot' && $action !== 'poll';
        if ($isWrite && (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST' || ! self::sameOrigin())) {
            return self::error('forbidden', 403);
        }

        if (! Settings::bool('chat_enabled')) {
            return self::error('disabled', 404);
        }

        $clientId = Whmcs::clientId();
        $input = self::input();
        $token = (string) ($_SERVER['HTTP_X_CHAT_TOKEN'] ?? ($input['token'] ?? ''));
        $language = self::language();
        Lang::use($language === 'fa' ? 'farsi' : 'english');

        // Nothing below writes to the session. Release its lock now, or one visitor's
        // slow AI reply would hold up their own polls (PHP locks the session file).
        self::releaseSession();

        if ($action === 'boot') {
            return self::boot($clientId, $token, $language);
        }

        if (! Settings::bool('chat_guests') && $clientId <= 0) {
            return self::error('login_required', 401);
        }

        if ($action === 'start') {
            $name = Text::clean((string) ($input['name'] ?? ''));
            $email = Text::clean((string) ($input['email'] ?? ''));
            if ($clientId <= 0 && Settings::bool('chat_guest_email')) {
                if (mb_strlen($name) < 2 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return self::error('identity_required', 422);
                }
            }
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return self::error('invalid_email', 422);
            }

            $existing = $token !== '' ? Chat::find($token, $clientId) : null;
            if ($existing && $existing->status === 'open') {
                return ['ok' => true, 'token' => $token, 'state' => Chat::state($existing), 'messages' => Chat::poll($existing, 0)['messages']];
            }

            if (Chat::tooManyStarts(self::ip())) {
                return self::error('rate_limited', 429);
            }

            $page = self::pageUrl((string) ($input['page'] ?? ''));
            $started = Chat::start($clientId, $name, $email, $page, self::ip(), (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), $language);

            return ['ok' => true, 'token' => $started['token'], 'state' => Chat::state($started['thread']), 'messages' => []];
        }

        $thread = Chat::find($token, $clientId);
        if (! $thread) {
            return self::error('no_thread', 404);
        }

        switch ($action) {
            case 'send':
                $result = Chat::send($thread, (string) ($input['body'] ?? ''), self::ip());
                if (! $result['ok']) {
                    return self::error($result['error'], $result['error'] === 'rate_limited' ? 429 : 422);
                }

                return ['ok' => true, 'message' => $result['message'], 'state' => Chat::state(Chat::thread((int) $thread->id))];

            case 'reply':
                $result = Chat::respond($thread);

                return ['ok' => true] + $result + ['state' => Chat::state(Chat::thread((int) $thread->id))];

            case 'poll':
                return ['ok' => true] + Chat::poll($thread, max(0, (int) ($_GET['after'] ?? 0)));

            case 'ticket':
                $result = Chat::toTicket($thread);
                if (! $result['ok']) {
                    return self::error($result['error'], 422);
                }

                return ['ok' => true, 'url' => $result['url'], 'tid' => $result['tid'] ?? '', 'state' => Chat::state(Chat::thread((int) $thread->id))] + Chat::poll($thread, max(0, (int) ($input['after'] ?? 0)));

            case 'end':
                Chat::close((int) $thread->id);

                return ['ok' => true];
        }

        return self::error('unknown_action', 404);
    }

    private static function boot(int $clientId, string $token, string $language): array
    {
        $guestBlocked = ! Settings::bool('chat_guests') && $clientId <= 0;
        $lang = $language === 'fa' ? 'farsi' : 'english';
        $clientName = '';
        if ($clientId > 0) {
            $details = Whmcs::api('GetClientsDetails', ['clientid' => $clientId]);
            $client = isset($details['client']) && is_array($details['client']) ? $details['client'] : $details;
            $clientName = (string) ($client['firstname'] ?? '');
        }

        $greeting = trim((string) Settings::get('chat_greeting'));
        if ($greeting === '') {
            $greeting = Lang::get('chat_greeting_default', ['brand' => Settings::brandName()], $lang);
        }

        $out = [
            'ok' => true,
            'config' => [
                'agent' => Settings::agentName(),
                'brand' => Settings::brandName(),
                'color' => (string) Settings::get('chat_color'),
                'text_color' => (string) Settings::get('chat_text_color'),
                'position' => (string) Settings::get('chat_position'),
                'greeting' => $greeting,
                'rtl' => $language === 'fa',
                'lang' => $language,
                'burst_ms' => (int) Settings::get('chat_burst_ms'),
                'max_length' => (int) Settings::get('chat_max_length'),
                'ai' => Settings::bool('chat_ai_enabled'),
                'credit' => Settings::bool('chat_credit'),
                'needs_identity' => $clientId <= 0 && Settings::bool('chat_guest_email'),
                'login_required' => $guestBlocked,
                'login_url' => Whmcs::systemUrl().'login.php',
                'client_name' => $clientName,
                'strings' => array_merge(Lang::group('w_', 'english'), Lang::group('w_', $lang)),
            ],
            'thread' => null,
        ];

        if (! $guestBlocked && $token !== '') {
            $thread = Chat::find($token, $clientId);
            if ($thread) {
                $messages = \WHMCS\Database\Capsule::table(Chat::MESSAGES)->where('thread_id', $thread->id)->orderByDesc('id')->limit(60)->get()->all();
                $out['thread'] = [
                    'state' => Chat::state($thread),
                    'messages' => array_map([Chat::class, 'present'], array_reverse($messages)),
                ];
            }
        }

        return $out;
    }

    /** fa | en for this visitor: the setting, then the client-area language. */
    public static function language(): string
    {
        $setting = (string) Settings::get('language');
        if ($setting === 'fa' || $setting === 'en') {
            return $setting;
        }

        return Lang::resolve(Whmcs::clientLanguage()) === 'farsi' ? 'fa' : 'en';
    }

    private static function input(): array
    {
        $raw = file_get_contents('php://input');
        $data = $raw !== false && $raw !== '' ? json_decode($raw, true) : null;

        return is_array($data) ? $data : $_POST;
    }

    private static function sameOrigin(): bool
    {
        if (($_SERVER['HTTP_X_NETARZ_CHAT'] ?? '') !== '1') {
            return false;
        }

        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin === '' || $origin === 'null') {
            return true; // same-origin requests may omit it; the custom header already rules out a cross-site form
        }

        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        $allowed = array_filter([
            strtolower((string) parse_url(Whmcs::systemUrl(), PHP_URL_HOST)),
            strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''))),
        ]);

        return in_array($host, $allowed, true);
    }

    private static function pageUrl(string $page): string
    {
        $page = Text::clean($page);

        return preg_match('#^https?://#i', $page) ? Text::limit($page, 500) : '';
    }

    public static function releaseSession(): void
    {
        if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public static function ip(): string
    {
        if (class_exists('\App') && method_exists('\App', 'getRemoteIp')) {
            try {
                return (string) \App::getRemoteIp();
            } catch (\Throwable $e) {
            }
        }

        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    private static function error(string $code, int $status): array
    {
        return ['ok' => false, 'error' => $code, 'message' => Lang::get('w_err_'.$code), '_status' => $status];
    }

    /** The response body, without the internal status field. */
    public static function json(array $payload): string
    {
        unset($payload['_status']);

        return (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Send a JSON response and stop WHMCS from rendering a page around it. */
    public static function emit(array $payload): void
    {
        $status = (int) ($payload['_status'] ?? 200);

        // WHMCS has already opened output buffers for its page template; drop them all.
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        if (! headers_sent()) {
            http_response_code($status);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, max-age=0');
            header('X-Content-Type-Options: nosniff');
            header('X-Robots-Tag: noindex');
        }
        echo self::json($payload);
    }
}
