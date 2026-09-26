<?php

namespace NetArz\WhmcsAi\Tests\Support;

use NetArz\WhmcsAi\Gateway;

/**
 * Stands in for https://netarz.ir/api/ai/v1 inside a test, through the
 * Gateway::$transport seam. Records every request; answers /me, /models,
 * /usage and /chat/completions with the gateway's real response shapes.
 */
final class FakeGateway
{
    /** @var array<int, array{method:string, url:string, headers:array, body:?array}> */
    public static $requests = [];

    /** @var array<int, array{status:int, body:mixed}|callable> queued /chat/completions answers */
    public static $chatQueue = [];

    /** @var float */
    public static $balanceUsd = 12.5;

    /** @var array{status:int, body:array}|null */
    public static $meOverride = null;

    public static function install(): void
    {
        self::$requests = [];
        self::$chatQueue = [];
        self::$balanceUsd = 12.5;
        self::$meOverride = null;

        Gateway::$transport = function (string $method, string $url, array $headers, ?string $body) {
            $decoded = $body !== null ? json_decode($body, true) : null;
            self::$requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $decoded];
            $path = (string) parse_url($url, PHP_URL_PATH);

            if (substr($path, -3) === '/me') {
                if (self::$meOverride) {
                    return self::respond(self::$meOverride['status'], self::$meOverride['body']);
                }

                return self::respond(200, [
                    'object' => 'account',
                    'balance' => ['usd' => number_format(self::$balanceUsd, 6, '.', ''), 'usd_display' => '$'.number_format(self::$balanceUsd, 2), 'toman_estimate' => (int) round(self::$balanceUsd * 238000)],
                    'status' => 'active',
                    'project' => ['id' => 7, 'name' => 'Pars Host WHMCS'],
                    'key' => ['name' => 'whmcs', 'prefix' => 'sk-ntz-v1-ab', 'rpm_limit' => 60, 'spend_limit_usd' => null, 'spent_usd' => '1.200000', 'expires_at' => null],
                    'limits' => ['concurrency' => 4, 'max_request_kb' => 512],
                ]);
            }

            if (strpos($path, '/models') !== false) {
                return self::respond(200, ['object' => 'list', 'data' => [
                    ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini', 'type' => 'chat', 'capabilities' => ['json' => true], 'pricing' => ['input_per_million' => '0.210000', 'output_per_million' => '0.840000', 'cached_input_per_million' => '0.105000']],
                    ['id' => 'deepseek-flash', 'name' => 'DeepSeek Flash', 'type' => 'chat', 'capabilities' => ['json' => true], 'pricing' => ['input_per_million' => '0.420000', 'output_per_million' => '1.680000']],
                ]]);
            }

            if (strpos($path, '/usage') !== false) {
                return self::respond(200, ['object' => 'usage', 'from' => '2026-09-01', 'to' => '2026-09-26', 'group' => 'day', 'total_usd' => '0.420000', 'data' => [['key' => '2026-09-26', 'requests' => 12, 'errors' => 0, 'input_tokens' => 40000, 'output_tokens' => 6000, 'usd' => '0.420000']]]);
            }

            if (strpos($path, '/chat/completions') !== false) {
                $next = array_shift(self::$chatQueue);
                if (is_callable($next)) {
                    $next = $next($decoded);
                }
                if ($next === null) {
                    $next = self::answer('answer', 'Default test answer.', 90);
                }

                return self::respond($next['status'], $next['body'], ['X-Request-Id' => 'req_'.count(self::$requests)]);
            }

            return self::respond(404, ['error' => ['message' => 'not found', 'type' => 'invalid_request_error', 'code' => 'not_found']]);
        };
    }

    public static function uninstall(): void
    {
        Gateway::$transport = null;
    }

    /** A well-formed agent answer as the model would send it. */
    public static function answer(string $action, string $reply, int $confidence = 90, string $reason = '', string $intent = 'other'): array
    {
        $content = json_encode(['action' => $action, 'reply' => $reply, 'confidence' => $confidence, 'intent' => $intent, 'handoff_reason' => $reason], JSON_UNESCAPED_UNICODE);

        return self::completion($content);
    }

    /** Any raw content as a completion (for malformed-output tests). */
    public static function completion(string $content, int $in = 1200, int $out = 80): array
    {
        return ['status' => 200, 'body' => [
            'id' => 'chatcmpl-test', 'object' => 'chat.completion', 'created' => time(), 'model' => 'gpt-4o-mini',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => 'stop']],
            'usage' => ['prompt_tokens' => $in, 'completion_tokens' => $out, 'total_tokens' => $in + $out],
        ]];
    }

    public static function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['error' => ['message' => $message, 'type' => 'error', 'code' => $code]]];
    }

    public static function chatRequests(): array
    {
        return array_values(array_filter(self::$requests, function ($r) {
            return strpos($r['url'], '/chat/completions') !== false;
        }));
    }

    private static function respond(int $status, $body, array $headers = []): array
    {
        return ['status' => $status, 'headers' => $headers + ['Content-Type' => 'application/json'], 'body' => json_encode($body, JSON_UNESCAPED_UNICODE)];
    }
}
