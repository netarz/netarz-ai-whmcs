<?php

namespace NetArz\WhmcsAi;

class GatewayError extends \RuntimeException
{
    /** @var int */
    public $status;

    /** @var string */
    public $errorCode;

    public function __construct(string $message, int $status, string $errorCode)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->errorCode = $errorCode;
    }

    /** Errors that mean "stop spending until the owner does something". */
    public function isAccountProblem(): bool
    {
        return in_array($this->errorCode, ['missing_api_key', 'invalid_api_key', 'insufficient_credit', 'account_suspended', 'key_expired', 'key_revoked'], true)
            || in_array($this->status, [401, 402, 403], true);
    }

    /** Errors worth one more try later (network, 429, 5xx). */
    public function isTransient(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}
