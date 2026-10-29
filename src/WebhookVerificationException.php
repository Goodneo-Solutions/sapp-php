<?php

declare(strict_types=1);

namespace Superroute\SApp;

/**
 * A webhook that failed verification. `errorCode` is one of `unknown_scheme`, `invalid_secret`, `missing_header`,
 * `invalid_timestamp`, `timestamp_out_of_tolerance`, `invalid_signature`, `invalid_payload`. Answer it with 400 (or
 * 401) and do not act on the body.
 */
final class WebhookVerificationException extends \RuntimeException
{
    public function __construct(public readonly string $errorCode, ?string $message = null)
    {
        parent::__construct($message ?? $errorCode);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }
}
