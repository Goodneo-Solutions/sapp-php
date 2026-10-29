<?php

declare(strict_types=1);

namespace Superroute\SApp;

/**
 * Every failed call. `status` is the HTTP status (0 when the request never got an answer), `errorCode` the server's
 * error code (`invalid_token`, `insufficient_scope`, `access_denied`, a platform app code such as `APP_ACCESS_DENIED`,
 * …) when it sent one, `body` the decoded answer.
 */
class SuperrouteException extends \RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly ?string $errorCode = null,
        ?string $message = null,
        public readonly mixed $body = null,
        public readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message ?? $errorCode ?? "Request failed with status {$status}", 0, $previous);
    }

    /** The person must sign in again: the session ended or was revoked. */
    public function signInRequired(): bool
    {
        return $this->status === 401 || $this->errorCode === 'invalid_grant';
    }

    /** @internal Builds the error of a failed answer. */
    public static function from(int $status, mixed $body, ?string $requestId = null): self
    {
        $b = is_array($body) ? $body : [];
        $text = static fn (string $key): ?string => isset($b[$key]) && is_string($b[$key]) && $b[$key] !== '' ? $b[$key] : null;
        $message = $b['message'] ?? null;
        $code = $text('error') ?? $text('code')
            ?? (is_string($message) && preg_match('/^[A-Z_]+$/', $message) === 1 ? $message : null);
        $description = $text('error_description')
            ?? (is_array($message) ? implode(' ', array_map(static fn ($m) => is_scalar($m) ? (string) $m : json_encode($m), $message)) : null)
            ?? (is_string($message) && $message !== '' ? $message : null)
            ?? $text('title');

        return new self($status, $code, $description, $body, $text('request_id') ?? $requestId);
    }
}
