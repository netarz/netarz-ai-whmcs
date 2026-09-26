<?php

namespace NetArz\WhmcsAi;

/**
 * A small client for the NetArz AI API (OpenAI-compatible, base /api/ai/v1).
 * The module talks to nothing else.
 */
class Gateway
{
    public const USER_AGENT = 'NetArz-WHMCS/'.Schema::VERSION.' (+https://github.com/netarz/netarz-ai-whmcs)';

    /** @var string */
    private $baseUrl;

    /** @var string */
    private $apiKey;

    /** @var int */
    private $timeout;

    /** @var callable|null test seam: fn(string $method, string $url, array $headers, ?string $body): array{status:int, headers:array, body:string} */
    public static $transport = null;

    public function __construct(?string $apiKey = null, ?string $baseUrl = null, int $timeout = 60)
    {
        $this->apiKey = $apiKey !== null ? $apiKey : Settings::apiKey();
        $this->baseUrl = rtrim($baseUrl !== null ? $baseUrl : Settings::baseUrl(), '/');
        $this->timeout = $timeout;
    }

    public function hasKey(): bool
    {
        return $this->apiKey !== '';
    }

    /** GET /me — balance, key and project. */
    public function me(): array
    {
        return $this->request('GET', '/me')['json'];
    }

    /** GET /models?type=chat — public, no key needed. */
    public function models(string $type = 'chat'): array
    {
        $res = $this->request('GET', '/models?type='.rawurlencode($type), null, false);

        return isset($res['json']['data']) && is_array($res['json']['data']) ? $res['json']['data'] : [];
    }

    /** GET /usage — daily or per-model totals for this key's project. */
    public function usage(string $from, string $to, string $group = 'day'): array
    {
        $query = http_build_query(['from' => $from, 'to' => $to, 'group' => $group]);

        return $this->request('GET', '/usage?'.$query)['json'];
    }

    /**
     * POST /chat/completions (non-streaming).
     *
     * @return array{json: array, request_id: string, latency_ms: int}
     */
    public function chat(array $payload): array
    {
        return $this->request('POST', '/chat/completions', $payload);
    }

    /**
     * @return array{json: array, request_id: string, latency_ms: int, status: int}
     *
     * @throws GatewayError
     */
    public function request(string $method, string $path, ?array $payload = null, bool $auth = true): array
    {
        if ($auth && $this->apiKey === '') {
            throw new GatewayError(Lang::get('err_no_key'), 0, 'missing_api_key');
        }

        $url = $this->baseUrl.$path;
        $headers = [
            'Accept: application/json',
            'User-Agent: '.self::USER_AGENT,
        ];
        if ($auth) {
            $headers[] = 'Authorization: Bearer '.$this->apiKey;
        }

        $body = null;
        if ($payload !== null) {
            $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json';
        }

        $started = microtime(true);
        $response = self::$transport
            ? call_user_func(self::$transport, $method, $url, $headers, $body)
            : $this->curl($method, $url, $headers, $body);
        $latency = (int) round((microtime(true) - $started) * 1000);

        $status = (int) $response['status'];
        $json = json_decode((string) $response['body'], true);
        $requestId = '';
        foreach ((array) $response['headers'] as $name => $value) {
            if (strtolower((string) $name) === 'x-request-id') {
                $requestId = (string) $value;
            }
        }

        if ($status < 200 || $status >= 300 || ! is_array($json)) {
            $error = is_array($json) && isset($json['error']) && is_array($json['error']) ? $json['error'] : [];
            $message = isset($error['message']) ? (string) $error['message'] : '';
            $code = isset($error['code']) ? (string) $error['code'] : '';

            if ($status === 0) {
                $message = Lang::get('err_network').($message !== '' ? ' ('.$message.')' : '');
                $code = 'network_error';
            } elseif ($message === '') {
                $message = Lang::get('err_http').' '.$status;
            }

            Whmcs::moduleLog($method.' '.$path, $payload, (string) $response['body'], $message);

            throw new GatewayError($message, $status, $code !== '' ? $code : self::codeFor($status));
        }

        return ['json' => $json, 'request_id' => $requestId, 'latency_ms' => $latency, 'status' => $status];
    }

    private static function codeFor(int $status): string
    {
        $map = [400 => 'invalid_request', 401 => 'invalid_api_key', 402 => 'insufficient_credit', 403 => 'forbidden', 404 => 'not_found', 413 => 'too_large', 429 => 'rate_limit_exceeded'];

        return $map[$status] ?? ($status >= 500 ? 'upstream_error' : 'http_'.$status);
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    private function curl(string $method, string $url, array $headers, ?string $body): array
    {
        $responseHeaders = [];
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[trim($parts[0])] = trim($parts[1]);
                }

                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['status' => 0, 'headers' => [], 'body' => json_encode(['error' => ['message' => $error]])];
        }

        return ['status' => $status, 'headers' => $responseHeaders, 'body' => (string) $raw];
    }
}
