<?php

namespace NetArz\WhmcsAi;

/**
 * One turn of the agent: knowledge in, a structured decision out.
 *
 * The model must answer with the JSON contract from Prompt. Anything that
 * does not parse, or parses into something unusable, becomes a handoff — the
 * customer never sees raw model output or an empty bubble.
 */
class Agent
{
    /**
     * @param string $channel chat | ticket | test
     * @param array<int, array{role:string, content:string}> $transcript oldest first; role user|assistant
     * @param int $clientId the customer this conversation belongs to (0 = guest)
     * @param int $refId thread id / ticket id, for the log
     */
    public static function reply(string $channel, array $transcript, int $clientId = 0, int $refId = 0): AgentReply
    {
        if (Settings::apiKey() === '') {
            return AgentReply::unavailable('missing_api_key', Lang::get('err_no_key'));
        }
        if (Usage::overBudget()) {
            return AgentReply::unavailable('budget', Lang::get('err_budget'));
        }
        if (! Balance::canSpend()) {
            return AgentReply::unavailable('low_balance', Lang::get('err_low_balance'));
        }

        $lastQuestion = '';
        $recentUser = [];
        foreach (array_reverse($transcript) as $turn) {
            if ($turn['role'] === 'user') {
                if ($lastQuestion === '') {
                    $lastQuestion = $turn['content'];
                }
                $recentUser[] = $turn['content'];
                if (count($recentUser) >= 3) {
                    break;
                }
            }
        }

        // Retrieval looks at the last few customer messages, not just the last one:
        // "how much is it?" only makes sense next to the message before it.
        $knowledge = Knowledge::forQuestion(implode("\n", array_reverse($recentUser)));
        $client = ClientContext::forClient($clientId);
        $language = self::language($lastQuestion);

        $system = Prompt::system($channel === 'test' ? 'chat' : $channel, $knowledge['text'], $client, $language);

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($transcript as $turn) {
            $messages[] = ['role' => $turn['role'] === 'assistant' ? 'assistant' : 'user', 'content' => Text::limit($turn['content'], 6000)];
        }

        $model = (string) Settings::get('model');
        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => (float) Settings::get('temperature'),
            'max_tokens' => (int) Settings::get('max_tokens'),
            'response_format' => ['type' => 'json_object'],
        ];

        $gateway = new Gateway();
        try {
            try {
                $res = $gateway->chat($payload);
            } catch (GatewayError $e) {
                // A few models refuse response_format; ask once more without it.
                if ($e->status !== 400 || stripos($e->getMessage(), 'response_format') === false) {
                    throw $e;
                }
                unset($payload['response_format']);
                $res = $gateway->chat($payload);
            }
        } catch (GatewayError $e) {
            Usage::record(['channel' => $channel, 'ref_id' => $refId, 'model' => $model, 'status' => 'error', 'error' => $e->errorCode.': '.$e->getMessage()]);
            if ($e->isAccountProblem()) {
                Cache::forget('balance');
            }

            return AgentReply::failed($e->errorCode, $e->getMessage(), $e->isTransient());
        }

        $json = $res['json'];
        $content = (string) ($json['choices'][0]['message']['content'] ?? '');
        $usage = isset($json['usage']) && is_array($json['usage']) ? $json['usage'] : [];
        $input = (int) ($usage['prompt_tokens'] ?? 0);
        $output = (int) ($usage['completion_tokens'] ?? 0);
        $cached = (int) ($usage['prompt_tokens_details']['cached_tokens'] ?? 0);
        $servedModel = (string) ($json['model'] ?? $model);

        $reply = AgentReply::parse($content, $channel);
        self::guardSilence($reply, $transcript, $lastQuestion, $language);
        self::guardSecrets($reply, $lastQuestion, $language);
        $reply->requestId = $res['request_id'];
        $reply->model = $servedModel;
        $reply->inputTokens = $input;
        $reply->outputTokens = $output;
        $reply->costUsd = Usage::cost($model, $input, $output, $cached);
        $reply->sources = $knowledge['sources'];

        Usage::record([
            'channel' => $channel, 'ref_id' => $refId, 'model' => $servedModel, 'request_id' => $res['request_id'],
            'input_tokens' => $input, 'output_tokens' => $output, 'cost_usd' => $reply->costUsd,
            'latency_ms' => $res['latency_ms'], 'status' => $reply->malformed ? 'error' : 'ok',
            'action' => $reply->action, 'confidence' => $reply->confidence,
            'error' => $reply->malformed ? 'malformed_output' : '',
        ]);

        return $reply;
    }

    /**
     * Models go quiet more often than they should. Silence is fine for a short
     * "thanks, bye" after something was answered; anything else — an attempt to
     * rewrite the rules, an off-topic question — gets one polite line instead of
     * leaving the visitor looking at nothing.
     */
    private static function guardSilence(AgentReply $reply, array $transcript, string $lastQuestion, string $language): void
    {
        if ($reply->action !== 'silent') {
            return;
        }

        $answeredBefore = false;
        foreach ($transcript as $turn) {
            if ($turn['role'] === 'assistant') {
                $answeredBefore = true;
            }
        }
        if ($answeredBefore && mb_strlen(trim($lastQuestion)) <= 40) {
            return;
        }

        $reply->action = 'answer';
        $reply->intent = 'other';
        $reply->reply = Lang::get('chat_redirect_default', ['brand' => Settings::brandName()], $language === 'fa' ? 'farsi' : 'english');
    }

    /**
     * A customer who types their password into a chat or ticket must be told to
     * change it. The prompt asks for that; this makes sure of it.
     */
    private static function guardSecrets(AgentReply $reply, string $lastQuestion, string $language): void
    {
        if ($reply->reply === '' || ! self::sharesSecret($lastQuestion)) {
            return;
        }
        // A reset link is not a warning: the customer must hear that this password is now exposed.
        $withoutLinks = preg_replace('#https?://\S+#u', '', $reply->reply);
        if (preg_match('/(change (it|this|your)|عوض|تغییر)/iu', (string) $withoutLinks)) {
            return;
        }

        $reply->reply .= "\n\n".Lang::get('secret_warning', [], $language === 'fa' ? 'farsi' : 'english');
    }

    /** "my password is X", «رمزم X هست», "pass: X" — a word for a secret followed by something that looks like one. */
    public static function sharesSecret(string $text): bool
    {
        return preg_match('/(password|passwd|pass\s*:|pwd|رمز|پسورد|کلمه\s*(ی|‌ی)?\s*عبور)[^\n]{0,30}?[\s:=]+\S{6,}/iu', $text) === 1
            && preg_match('/\S*[0-9!@#$%^&*]\S*/u', $text) === 1;
    }

    /**
     * fa | en | auto: the owner's setting, else the language of the customer's
     * own last message. Pinning it matters: an English question must never get
     * a Persian answer just because the prompt carries Persian style notes.
     */
    public static function language(string $sample): string
    {
        $setting = (string) Settings::get('language');
        if ($setting === 'fa' || $setting === 'en') {
            return $setting;
        }
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $sample)) {
            return Text::isRtl($sample) ? 'fa' : 'auto';
        }
        if (preg_match('/[A-Za-z]{2,}/', $sample)) {
            return 'en';
        }

        return 'auto';
    }
}
