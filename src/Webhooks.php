<?php

declare(strict_types=1);

namespace Superroute\SApp;

/**
 * Superroute webhook verification: Standard Webhooks, the native signatures each sender still sends, and CloudEvents
 * envelopes — the same rules, error codes and test vectors in every language of sApp.
 *
 *     $event = Webhooks::verify(file_get_contents('php://input'), getallheaders(), getenv('SUPERROUTE_WEBHOOK_SECRET'));
 *     if (alreadySeen($event->id)) { return; }   // the same event can arrive more than once
 *
 * Schemes: `standard` (Standard Webhooks, the default), `tenant`, `partner_locker`, `tpd`, `loading_plan`,
 * `open_platform`, `dataset`. Pass the raw body exactly as received, never a re-encoded array.
 */
final class Webhooks
{
    public const SCHEMES = ['standard', 'tenant', 'partner_locker', 'tpd', 'loading_plan', 'open_platform', 'dataset'];
    /** Seconds a signed timestamp may be away from now. */
    public const DEFAULT_TOLERANCE = 300;
    /**
     * Tenant webhooks sign the time the event was dispatched and keep it on every retry (5 tries over about 3 hours),
     * so their default tolerance covers the retries. Pair it with de-duplication on the event id.
     */
    public const TENANT_TOLERANCE = 12600;

    private const TYPE_PREFIX = 'ca.superroute.';

    /**
     * @param array<string, string|list<string>> $headers the request headers (PSR-7 getHeaders() works too)
     * @param string|list<string> $secret a whsec_ secret or the plain secret; several during a rotation
     * @param int|null $tolerance seconds; 300, or 12600 for `tenant`
     * @param int|null $now seconds since the epoch
     * @throws WebhookVerificationException
     */
    public static function verify(string $body, array $headers, string|array $secret, string $scheme = 'standard', ?int $tolerance = null, ?int $now = null): WebhookEvent
    {
        if (!in_array($scheme, self::SCHEMES, true)) {
            throw new WebhookVerificationException('unknown_scheme', "Unknown webhook scheme {$scheme}.");
        }
        $secrets = is_array($secret) ? array_values($secret) : [$secret];
        if ($secrets === []) {
            throw new WebhookVerificationException('invalid_secret', 'No webhook secret was given.');
        }
        $keys = array_map([self::class, 'keyOf'], $secrets);
        $lower = [];
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $value = $value[0] ?? null;
            }
            if ($value !== null) {
                $lower[strtolower((string) $name)] = (string) $value;
            }
        }
        $read = static fn (string $name): ?string => $lower[strtolower($name)] ?? null;
        $now ??= time();
        $tolerance ??= $scheme === 'tenant' ? self::TENANT_TOLERANCE : self::DEFAULT_TOLERANCE;

        self::checkSignature($scheme, $read, $body, $keys, $now, $tolerance);
        try {
            $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new WebhookVerificationException('invalid_payload', 'The webhook body is not JSON.');
        }

        return self::eventOf($read, $payload);
    }

    private static function keyOf(mixed $secret): string
    {
        if (!is_string($secret) || $secret === '') {
            throw new WebhookVerificationException('invalid_secret', 'The webhook secret is empty.');
        }
        if (str_starts_with($secret, 'whsec_')) {
            $encoded = substr($secret, 6);
            $key = preg_match('#^[A-Za-z0-9+/]*={0,2}$#', $encoded) === 1 && strlen($encoded) % 4 === 0 ? base64_decode($encoded, true) : false;
            if ($key === false || $key === '') {
                throw new WebhookVerificationException('invalid_secret', 'The whsec_ secret is not valid base64.');
            }

            return $key;
        }

        return $secret;
    }

    private static function need(callable $read, string $name): string
    {
        $value = $read($name);
        if ($value === null || $value === '') {
            throw new WebhookVerificationException('missing_header', "The {$name} header is missing.");
        }

        return $value;
    }

    private static function checkTime(string $raw, int $now, int $tolerance): string
    {
        $raw = trim($raw);
        if (preg_match('/^\d+$/', $raw) !== 1) {
            throw new WebhookVerificationException('invalid_timestamp', 'The webhook timestamp is not a number of seconds.');
        }
        if (abs($now - (int) $raw) > $tolerance) {
            throw new WebhookVerificationException('timestamp_out_of_tolerance', 'The webhook timestamp is outside the tolerance.');
        }

        return $raw;
    }

    /** @return list<string> the values after $prefix among the entries of a signature header */
    private static function entries(string $header, string $separator, string $prefix): array
    {
        $out = [];
        foreach (preg_split($separator, $header) ?: [] as $entry) {
            if (str_starts_with($entry, $prefix)) {
                $out[] = substr($entry, strlen($prefix));
            }
        }

        return $out;
    }

    /** @param list<string> $keys */
    private static function checkSignature(string $scheme, callable $read, string $body, array $keys, int $now, int $tolerance): void
    {
        switch ($scheme) {
            case 'standard':
                $id = self::need($read, 'webhook-id');
                $ts = self::need($read, 'webhook-timestamp');
                $header = self::need($read, 'webhook-signature');
                $ts = self::checkTime($ts, $now, $tolerance);
                $given = self::entries($header, '/ /', 'v1,');
                $expected = static fn (string $key): string => base64_encode(hash_hmac('sha256', "{$id}.{$ts}." . $body, $key, true));
                break;
            case 'tenant':
                $ts = self::need($read, 'X-Webhook-Timestamp');
                $header = self::need($read, 'X-Webhook-Signature-V2');
                $ts = self::checkTime($ts, $now, $tolerance);
                $given = [trim($header)];
                $expected = static fn (string $key): string => hash_hmac('sha256', "{$ts}." . $body, $key);
                break;
            case 'partner_locker':
            case 'tpd':
                $ts = self::need($read, 'X-Webhook-Timestamp');
                $delivery = self::need($read, 'X-Webhook-Delivery-Id');
                $header = self::need($read, 'X-Webhook-Signature');
                $ts = self::checkTime($ts, $now, $tolerance);
                $given = self::entries($header, '/[ ,]+/', 'v1=');
                $expected = static fn (string $key): string => base64_encode(hash_hmac('sha256', "{$ts}\n{$delivery}\n" . $body, $key, true));
                break;
            case 'loading_plan':
            case 'open_platform':
                $prefix = $scheme === 'open_platform' ? 'X-Open' : 'X-Webhook';
                $ts = self::need($read, "{$prefix}-Timestamp");
                $header = self::need($read, "{$prefix}-Signature");
                $ts = self::checkTime($ts, $now, $tolerance);
                $given = self::entries($header, '/[ ,]+/', 'v1=');
                $expected = static fn (string $key): string => hash_hmac('sha256', "{$ts}." . $body, $key);
                break;
            default: // dataset
                $header = self::need($read, 'X-Webhook-Signature');
                // Sent but not signed: it still turns away a plain replay of an old request.
                $ts = $read('X-Webhook-Timestamp');
                if ($ts !== null && $ts !== '') {
                    self::checkTime($ts, $now, $tolerance);
                }
                $given = [trim($header)];
                $expected = static fn (string $key): string => hash_hmac('sha256', $body, $key);
        }
        foreach ($keys as $key) {
            $signature = $expected($key);
            foreach ($given as $candidate) {
                if (hash_equals($signature, $candidate)) {
                    return;
                }
            }
        }
        throw new WebhookVerificationException('invalid_signature', 'No signature matches the secret.');
    }

    private static function str(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function int(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        return is_string($value) && preg_match('/^-?\d+$/', $value) === 1 ? (int) $value : null;
    }

    private static function stripType(?string $type): ?string
    {
        return $type !== null && str_starts_with($type, self::TYPE_PREFIX) ? substr($type, strlen(self::TYPE_PREFIX)) : $type;
    }

    private static function eventOf(callable $read, mixed $payload): WebhookEvent
    {
        $object = is_array($payload) && !array_is_list($payload) ? $payload : [];
        $contentType = strtolower((string) $read('Content-Type'));
        if (str_starts_with($contentType, 'application/cloudevents+json')
            || (array_key_exists('specversion', $object) && array_key_exists('data', $object) && self::str($object['type'] ?? null) !== null && self::str($object['id'] ?? null) !== null)) {
            return new WebhookEvent(
                self::str($object['id'] ?? null), self::stripType(self::str($object['type'] ?? null)), self::str($object['time'] ?? null),
                self::str($object['subject'] ?? null), self::str($object['source'] ?? null), self::int($object['srbusiness'] ?? null),
                null, 'ce_structured', $object['data'] ?? null, $payload,
            );
        }
        if (self::str($read('ce-specversion')) !== null) {
            return new WebhookEvent(
                self::str($read('ce-id')), self::stripType(self::str($read('ce-type'))), self::str($read('ce-time')),
                self::str($read('ce-subject')), self::str($read('ce-source')), self::int($read('ce-srbusiness')),
                self::int($read('ce-srattempt')), 'ce_binary', $payload, $payload,
            );
        }
        $first = static function (mixed ...$values): ?string {
            foreach ($values as $value) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            return null;
        };

        return new WebhookEvent(
            $first($read('webhook-id'), $read('X-Webhook-Event-Id'), $read('X-Webhook-Id'), $read('X-Open-Delivery'), $object['event_id'] ?? null),
            $first($read('X-Webhook-Event-Type'), $read('X-Open-Event'), $read('X-Webhook-Event'), $object['event_type'] ?? null, $object['event'] ?? null),
            $first($object['occurred_at'] ?? null, $object['event_time'] ?? null, $object['timestamp'] ?? null),
            null, null, null, null, 'native', $payload, $payload,
        );
    }
}
