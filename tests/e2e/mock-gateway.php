<?php
/**
 * A stand-in for https://netarz.ir/api/ai/v1 for the end-to-end run, served by
 * `php -S`. It checks the key like the real gateway and plays a model that
 * answers only from the KNOWLEDGE block it is sent — so the run proves the
 * module sends the right facts, not that the mock knows them.
 * Every request is appended to $NTZ_E2E_LOG for the browser script to inspect.
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$log = getenv('NTZ_E2E_LOG') ?: sys_get_temp_dir().'/netarz-ai-whmcs-e2e-gateway.log';
$body = file_get_contents('php://input');
$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
file_put_contents($log, json_encode(['path' => $path, 'auth' => $auth !== '', 'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '', 'body' => json_decode((string) $body, true)], JSON_UNESCAPED_UNICODE)."\n", FILE_APPEND);

header('Content-Type: application/json');
header('X-Request-Id: req_e2e_'.substr(md5(microtime()), 0, 10));

function out($status, $data)
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

if ($path === '/api/ai/v1/models') {
    out(200, ['object' => 'list', 'data' => [
        ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o mini', 'type' => 'chat', 'capabilities' => ['json' => true], 'pricing' => ['input_per_million' => '0.210000', 'output_per_million' => '0.840000']],
        ['id' => 'deepseek-flash', 'name' => 'DeepSeek Flash', 'type' => 'chat', 'capabilities' => ['json' => true], 'pricing' => ['input_per_million' => '0.420000', 'output_per_million' => '1.680000']],
    ]]);
    return true;
}

if ($auth !== 'Bearer sk-ntz-v1-e2etestkey0123456789ab') {
    out(401, ['error' => ['message' => 'Invalid API key.', 'type' => 'authentication_error', 'code' => 'invalid_api_key']]);
    return true;
}

if ($path === '/api/ai/v1/me') {
    out(200, ['object' => 'account', 'balance' => ['usd' => '8.412300', 'usd_display' => '$8.41', 'toman_estimate' => 2011000], 'status' => 'active',
        'project' => ['id' => 3, 'name' => 'Pars Host support'], 'key' => ['name' => 'whmcs', 'prefix' => 'sk-ntz-v1-e2', 'rpm_limit' => 60, 'spend_limit_usd' => null, 'spent_usd' => '0.4', 'expires_at' => null],
        'limits' => ['concurrency' => 4, 'max_request_kb' => 512]]);
    return true;
}

if ($path === '/api/ai/v1/usage') {
    out(200, ['object' => 'usage', 'total_usd' => '0.01', 'data' => []]);
    return true;
}

if ($path === '/api/ai/v1/chat/completions') {
    $req = json_decode((string) $body, true);
    $system = (string) ($req['messages'][0]['content'] ?? '');
    $last = '';
    foreach ($req['messages'] as $m) {
        if ($m['role'] === 'user') {
            $last = (string) $m['content'];
        }
    }
    $fa = preg_match('/[\x{0600}-\x{06FF}]/u', $last) === 1;
    usleep(900000); // a model takes a moment; the widget shows it typing

    $answer = ['action' => 'answer', 'confidence' => 90, 'intent' => 'other', 'handoff_reason' => ''];
    if (preg_match('/refund|money back|بازگشت|پول/iu', $last)) {
        $answer = ['action' => 'handoff', 'confidence' => 35, 'intent' => 'complaint', 'handoff_reason' => 'refund request',
            'reply' => $fa ? 'متأسفم که این‌طور شد. پیامتون رو به همکارم دادم و همین‌جا جواب می‌ده.' : 'I am sorry about that. I have passed this to a colleague, who will reply here.'];
    } elseif (preg_match('/(Shared Hosting › Starter — [^|\n]+)/u', $system, $m) && preg_match('/starter|price|cost|much|قیمت|هزینه|چند/iu', $last)) {
        $answer['reply'] = $fa ? 'پلن Starter: '.trim($m[1]).'. برای سفارش از فرم سفارش اقدام کنید.' : 'Our '.trim($m[1]).'.';
    } elseif (preg_match('/nameserver|نیم ?سرور|dns/iu', $last) && preg_match('/(ns1\.[a-z.]+)/', $system, $m)) {
        $answer['reply'] = $fa ? 'نیم‌سرورها را روی '.$m[1].' بگذارید.' : 'Please point your domain to '.$m[1].' and ns2.';
    } elseif (preg_match('/Support hours: ([^\n]+)/', $system, $m) && preg_match('/hour|open|ساعت/iu', $last)) {
        $answer['reply'] = 'We are here '.$m[1];
    } else {
        $answer['reply'] = $fa ? 'سلام، بفرمایید. چه کمکی از دستم برمیاد؟' : 'Hello! How can I help you today?';
    }

    out(200, ['id' => 'chatcmpl-e2e', 'object' => 'chat.completion', 'created' => time(), 'model' => $req['model'],
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($answer, JSON_UNESCAPED_UNICODE)], 'finish_reason' => 'stop']],
        'usage' => ['prompt_tokens' => (int) (mb_strlen($system) / 4), 'completion_tokens' => 60, 'total_tokens' => (int) (mb_strlen($system) / 4) + 60]]);
    return true;
}

out(404, ['error' => ['message' => 'Not found', 'code' => 'not_found']]);
return true;
