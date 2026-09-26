<?php

namespace NetArz\WhmcsAi;

/**
 * The NetArz AI credit behind the API key: read from GET /me, cached for five
 * minutes, and used to pause the agent before it runs dry.
 */
class Balance
{
    public const TOPUP_URL = 'https://netarz.ir/ai/topup';

    public const PANEL_URL = 'https://netarz.ir/ai';

    /**
     * @return array{ok:bool, usd:float, usd_display:string, toman:int, status:string, project:string, key:string, rpm:int, error:string, code:string, checked_at:int}
     */
    public static function get(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = Cache::get('balance');
            if (is_array($cached)) {
                return $cached;
            }
        }

        $gateway = new Gateway(null, null, 15);
        if (! $gateway->hasKey()) {
            return self::failure(Lang::get('err_no_key'), 'missing_api_key');
        }

        try {
            $me = $gateway->me();
            $result = [
                'ok' => true,
                'usd' => (float) ($me['balance']['usd'] ?? 0),
                'usd_display' => (string) ($me['balance']['usd_display'] ?? ''),
                'toman' => (int) ($me['balance']['toman_estimate'] ?? 0),
                'status' => (string) ($me['status'] ?? ''),
                'project' => (string) ($me['project']['name'] ?? ''),
                'key' => (string) ($me['key']['name'] ?? '').' ('.($me['key']['prefix'] ?? '').'…)',
                'rpm' => (int) ($me['key']['rpm_limit'] ?? 0),
                'error' => '',
                'code' => '',
                'checked_at' => Clock::time(),
            ];
        } catch (GatewayError $e) {
            $result = self::failure($e->getMessage(), $e->errorCode);
        }

        Cache::put('balance', $result, $result['ok'] ? 300 : 60);

        return $result;
    }

    /** Is there enough credit (and a working key) to let the agent answer? */
    public static function canSpend(): bool
    {
        $balance = self::get();
        if (! $balance['ok']) {
            // A network blip must not silence the agent; a bad key or empty account must.
            return ! in_array($balance['code'], ['missing_api_key', 'invalid_api_key', 'insufficient_credit', 'forbidden'], true);
        }

        return $balance['usd'] > (float) Settings::get('min_balance') && $balance['status'] !== 'suspended';
    }

    /** Email the admins once a day when credit is low or the key stopped working. Called from cron. */
    public static function alertIfLow(): ?string
    {
        if (! Settings::bool('balance_alerts') || Settings::apiKey() === '') {
            return null;
        }

        $balance = self::get(true);
        $low = $balance['ok'] && $balance['usd'] <= (float) Settings::get('min_balance') * 2;
        $broken = ! $balance['ok'] && in_array($balance['code'], ['invalid_api_key', 'insufficient_credit', 'forbidden'], true);
        if (! $low && ! $broken) {
            return null;
        }

        $today = date('Y-m-d', Clock::time());
        if (Settings::get('alert_sent_on') === $today) {
            return null;
        }

        $subject = Lang::get('alert_subject');
        $body = $low
            ? Lang::get('alert_low_body', ['balance' => $balance['usd_display'], 'min' => '$'.number_format((float) Settings::get('min_balance'), 2), 'url' => self::TOPUP_URL])
            : Lang::get('alert_broken_body', ['error' => $balance['error'], 'url' => self::PANEL_URL]);

        Whmcs::api('SendAdminEmail', ['customsubject' => $subject, 'custommessage' => nl2br(Text::e($body)), 'type' => 'system']);
        Whmcs::log($subject.' — '.$body);
        Settings::set('alert_sent_on', $today);

        return $low ? 'low' : 'broken';
    }

    private static function failure(string $message, string $code): array
    {
        return ['ok' => false, 'usd' => 0.0, 'usd_display' => '', 'toman' => 0, 'status' => '', 'project' => '', 'key' => '', 'rpm' => 0, 'error' => $message, 'code' => $code, 'checked_at' => Clock::time()];
    }
}
