<?php

namespace NetArz\WhmcsAi;

/** The agent's decision for one turn. */
class AgentReply
{
    public const ACTIONS = ['answer', 'handoff', 'silent'];

    /** @var string answer | handoff | silent | unavailable | failed */
    public $action = 'handoff';

    /** @var string */
    public $reply = '';

    /** @var int */
    public $confidence = 0;

    /** @var string */
    public $intent = 'other';

    /** @var string */
    public $handoffReason = '';

    /** @var bool the model broke the output contract */
    public $malformed = false;

    /** @var string */
    public $errorCode = '';

    /** @var string */
    public $error = '';

    /** @var bool worth retrying later */
    public $transient = false;

    /** @var string */
    public $requestId = '';

    /** @var string */
    public $model = '';

    /** @var int */
    public $inputTokens = 0;

    /** @var int */
    public $outputTokens = 0;

    /** @var float */
    public $costUsd = 0.0;

    /** @var array */
    public $sources = [];

    public static function parse(string $content, string $channel): self
    {
        $r = new self();
        $data = self::decode($content);

        if (! is_array($data) || ! isset($data['action']) || ! in_array($data['action'], self::ACTIONS, true)) {
            // Plain prose instead of JSON: never shown blindly, handed to a person as a draft.
            $r->malformed = true;
            $r->action = 'handoff';
            $r->reply = '';
            $r->handoffReason = 'malformed_output';
            $r->intent = 'other';
            $text = Text::clean(Text::plain($content));
            $r->error = $text !== '' ? Text::limit($text, 2000) : '';

            return $r;
        }

        $r->action = (string) $data['action'];
        $r->reply = self::tidy((string) ($data['reply'] ?? ''));
        $r->confidence = max(0, min(100, (int) ($data['confidence'] ?? 0)));
        $r->intent = preg_replace('/[^a-z_]/', '', strtolower((string) ($data['intent'] ?? 'other'))) ?: 'other';
        $r->handoffReason = Text::limit(Text::clean((string) ($data['handoff_reason'] ?? '')), 250);

        if ($r->action === 'silent' && $channel === 'ticket') {
            // A ticket always deserves an answer from someone.
            $r->action = 'handoff';
            $r->handoffReason = $r->handoffReason ?: 'model_chose_silence';
        }
        if ($r->action === 'answer' && $r->reply === '') {
            $r->action = 'handoff';
            $r->malformed = true;
            $r->handoffReason = 'empty_reply';
        }
        if ($r->action === 'silent') {
            $r->reply = '';
        }

        return $r;
    }

    public static function unavailable(string $code, string $message): self
    {
        $r = new self();
        $r->action = 'unavailable';
        $r->errorCode = $code;
        $r->error = $message;

        return $r;
    }

    public static function failed(string $code, string $message, bool $transient): self
    {
        $r = new self();
        $r->action = 'failed';
        $r->errorCode = $code;
        $r->error = $message;
        $r->transient = $transient;

        return $r;
    }

    public function answered(): bool
    {
        return $this->action === 'answer' && $this->reply !== '';
    }

    public function isHandoff(): bool
    {
        return $this->action === 'handoff';
    }

    /** Did the model actually run (as opposed to being skipped or failing)? */
    public function ran(): bool
    {
        return in_array($this->action, self::ACTIONS, true);
    }

    /** JSON, JSON inside ``` fences, or JSON with chatter around it. */
    private static function decode(string $content)
    {
        $content = trim($content);
        if ($content === '') {
            return null;
        }

        $data = json_decode($content, true);
        if (is_array($data)) {
            return $data;
        }

        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/su', $content, $m)) {
            $data = json_decode($m[1], true);
            if (is_array($data)) {
                return $data;
            }
        }

        $start = strpos($content, '{');
        $end = strrpos($content, '}');
        if ($start !== false && $end !== false && $end > $start) {
            $data = json_decode(substr($content, $start, $end - $start + 1), true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    /** Clean the customer-facing text: no markdown emphasis, no stray whitespace. */
    private static function tidy(string $text): string
    {
        $text = Text::clean($text);
        $text = preg_replace('/\*\*(.+?)\*\*/su', '$1', $text);
        // A markdown link would show as raw brackets in the widget and the ticket: keep text and URL.
        $text = preg_replace('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', '$1: $2', (string) $text);
        $text = preg_replace('/^#{1,6}\s+/mu', '', (string) $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", (string) $text);

        return trim((string) $text);
    }
}
