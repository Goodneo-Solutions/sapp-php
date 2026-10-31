<?php

declare(strict_types=1);

namespace Superroute\SApp;

/**
 * Every failed call. `status` is the HTTP status (0 when the request never got an answer), `errorCode` the server's
 * error code (`invalid_token`, `insufficient_scope`, `access_denied`, a platform app code such as `APP_ACCESS_DENIED`,
 * …) when it sent one, `body` the decoded answer.
 *
 * An answer `{"result": false, …}` is a failed call too, whatever its status (Superroute sometimes answers a refusal
 * with 200): `errors` holds its field messages (field => list of messages), `reasons` and `data` what it sent beside
 * them.
 *
 * `headers` are the refused answer's headers (names in lower case, several values of one name joined with ", "; empty
 * when there was no answer), `retryAfter` its Retry-After in whole seconds (null when absent or not understood) and
 * `requestId` the answer's `request_id`, else its X-Request-Id header.
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
        /** @var array<string, list<string>> */
        public readonly array $errors = [],
        public readonly mixed $reasons = null,
        public readonly mixed $data = null,
        /** @var array<string, string> */
        public readonly array $headers = [],
        public readonly ?int $retryAfter = null,
    ) {
        parent::__construct($message ?? $errorCode ?? "Request failed with status {$status}", 0, $previous);
    }

    /**
     * Header names in lower case; several values of one name (a list) joined with ", ".
     *
     * @internal
     * @param array<string, string|list<string>> $headers
     * @return array<string, string>
     */
    public static function lowerHeaders(array $headers): array
    {
        $out = [];
        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            $text = is_array($value) ? implode(', ', array_map(static fn ($v): string => (string) $v, $value)) : (string) $value;
            $out[$key] = isset($out[$key]) ? $out[$key] . ', ' . $text : $text;
        }

        return $out;
    }

    /**
     * Retry-After in whole seconds: a non-negative integer as is, an HTTP-date as the seconds until it (rounded up, at
     * least 0), anything else null.
     *
     * @internal
     */
    public static function retryAfterSeconds(?string $value, ?float $now = null): ?int
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        if (preg_match('/^[0-9]+$/', $value) === 1) {
            return (int) $value;
        }
        $date = \DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $value, new \DateTimeZone('UTC'));
        if ($date === false) {
            return null;
        }

        return max(0, (int) ceil((float) $date->format('U') - ($now ?? microtime(true))));
    }

    /**
     * The same error with the refused answer's headers: `headers`, `retryAfter`, and `requestId` from X-Request-Id
     * when it has none.
     *
     * @internal
     * @param array<string, string|list<string>> $headers
     */
    public static function withAnswer(int $status, ?string $errorCode, ?string $message, mixed $body, array $headers, ?string $requestId = null): self
    {
        $lowered = self::lowerHeaders($headers);
        $b = is_array($body) ? $body : [];
        $requestId ??= isset($b['request_id']) && is_string($b['request_id']) && $b['request_id'] !== '' ? $b['request_id'] : null;

        return new self(
            $status, $errorCode, $message, $body, $requestId ?? $lowered['x-request-id'] ?? null, null,
            [], null, null, $lowered, self::retryAfterSeconds($lowered['retry-after'] ?? null),
        );
    }

    /** The person must sign in again: the session ended or was revoked. */
    public function signInRequired(): bool
    {
        return $this->status === 401 || $this->errorCode === 'invalid_grant';
    }

    /** @internal Builds the error of a failed answer. */
    /** @param array<string, string|list<string>> $headers */
    public static function from(int $status, mixed $body, ?string $requestId = null, array $headers = []): self
    {
        $lowered = self::lowerHeaders($headers);
        $requestId ??= $lowered['x-request-id'] ?? null;
        $b = is_array($body) ? $body : [];
        $text = static fn (string $key): ?string => isset($b[$key]) && is_string($b[$key]) && $b[$key] !== '' ? $b[$key] : null;
        $message = $b['message'] ?? null;
        $code = $text('error') ?? $text('code')
            ?? (is_string($message) && preg_match('/^[A-Z_]+$/', $message) === 1 ? $message : null);
        $description = $text('error_description')
            ?? (is_array($message) ? implode(' ', array_map(static fn ($m) => is_scalar($m) ? (string) $m : json_encode($m), $message)) : null)
            ?? (is_string($message) && $message !== '' ? $message : null)
            ?? $text('title');

        $code ??= isset($b['code']) && is_int($b['code']) ? (string) $b['code'] : null;
        $errors = [];
        if (is_array($b['errors'] ?? null) && !array_is_list($b['errors'])) {
            foreach ($b['errors'] as $field => $value) {
                if ($value === null) {
                    continue;
                }
                $errors[(string) $field] = array_map(
                    static fn ($v): string => Http::text($v),
                    is_array($value) && array_is_list($value) ? $value : [$value],
                );
            }
        }

        return new self(
            $status, $code, $description, $body, $text('request_id') ?? $requestId, null,
            $errors, $b['reasons'] ?? null, $b['data'] ?? null, $lowered,
            self::retryAfterSeconds($lowered['retry-after'] ?? null),
        );
    }
}
