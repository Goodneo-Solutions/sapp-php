<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** @internal The request building and sending both clients share. */
final class Http
{
    public const SDK_VERSION = '0.2.1';
    public const DEFAULT_BASE_URL = 'https://open.superroute.ca';

    public static function defaultTransport(): Transport
    {
        return extension_loaded('curl') ? new CurlTransport() : new StreamTransport();
    }

    public static function text(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return (string) (int) $value;
        }
        if (is_array($value) || is_object($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /** @param array<string, mixed> $params */
    public static function url(string $baseUrl, Operation $operation, array $params): string
    {
        $path = (string) preg_replace_callback('/\{([^}]+)\}/', static function (array $m) use ($params): string {
            $value = $params[$m[1]] ?? null;
            if ($value === null || self::text($value) === '') {
                return '';
            }

            return rawurlencode(self::text($value));
        }, $operation->path);
        $path = (string) preg_replace('#/{2,}#', '/', $path);
        $path = rtrim($path, '/');
        $url = $baseUrl . ($path === '' ? '/' : $path);
        $pairs = [];
        foreach ($operation->query as $name) {
            $value = $params[$name] ?? null;
            if ($value === null) {
                continue;
            }
            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                $pairs[] = rawurlencode($name) . '=' . rawurlencode(self::text($item));
            }
        }

        return $pairs === [] ? $url : $url . '?' . implode('&', $pairs);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param list<OperationFile> $files
     * @return array{0: ?string, 1: array<string, string>} the encoded body and its headers
     */
    public static function encodeBody(?string $kind, ?array $body, array $files = []): array
    {
        if ($kind === null || ($body === null && $files === [])) {
            return [null, []];
        }
        $body ??= [];
        if ($kind === 'form') {
            return [self::form($body), ['Content-Type' => 'application/x-www-form-urlencoded']];
        }
        if ($kind === 'multipart') {
            $boundary = 'sapp-' . bin2hex(random_bytes(12));
            $out = '';
            foreach ($body as $key => $value) {
                if ($value === null) {
                    continue;
                }
                foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                    $name = is_array($value) && array_is_list($value) ? $key . '[]' : (string) $key;
                    $out .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"" . self::quote($name) . "\"\r\n\r\n" . self::text($item) . "\r\n";
                }
            }
            foreach ($files as $file) {
                $out .= "--{$boundary}\r\nContent-Disposition: form-data; name=\"" . self::quote($file->field) . '"; filename="'
                    . self::quote($file->filename) . "\"\r\nContent-Type: {$file->contentType}\r\n\r\n{$file->content}\r\n";
            }
            $out .= "--{$boundary}--\r\n";

            return [$out, ['Content-Type' => "multipart/form-data; boundary={$boundary}"]];
        }

        return [self::json($body), ['Content-Type' => 'application/json']];
    }

    /** JSON with an empty array sent as an object, since every body is an object. */
    public static function json(array $body): string
    {
        return (string) json_encode($body === [] ? new \stdClass() : $body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /** @param array<string, mixed> $fields */
    public static function form(array $fields): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($value === null) {
                continue;
            }
            $pairs[] = rawurlencode((string) $key) . '=' . rawurlencode(self::text($value));
        }

        return implode('&', $pairs);
    }

    private static function quote(string $value): string
    {
        return str_replace(['"', "\r", "\n"], ['%22', '%0D', '%0A'], $value);
    }

    /**
     * Repeatable calls are retried on a lost connection, 502–504, and 429 with Retry-After. Writes are never retried.
     *
     * @param array<string, string> $headers
     * @param callable(float): void $sleep seconds
     */
    public static function send(Transport $transport, string $method, string $url, array $headers, ?string $body, int $retries, bool $retryable, callable $sleep): Response
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $transport->send($method, $url, $headers, $body);
            } catch (TransportException $e) {
                if ($retryable && $attempt < $retries) {
                    $sleep(0.3 * 2 ** $attempt);
                    continue;
                }
                throw new SuperrouteException(0, 'network_error', 'The request could not reach Superroute.', null, null, $e);
            }
            $transient = in_array($response->status, [502, 503, 504], true);
            $retryAfter = $response->header('Retry-After');
            $throttled = $response->status === 429 && $retryAfter !== null;
            if ($retryable && $attempt < $retries && ($transient || $throttled)) {
                $sleep($throttled ? min(30, max(1, (int) $retryAfter)) : 0.3 * 2 ** $attempt);
                continue;
            }

            return $response;
        }
    }

    public static function decode(Response $response): mixed
    {
        if ($response->body === '') {
            return null;
        }
        try {
            return json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $response->body;
        }
    }

    public static function orThrow(Response $response): mixed
    {
        $body = self::decode($response);
        if ($response->status >= 400) {
            throw SuperrouteException::from($response->status, $body, $response->header('X-Request-Id'));
        }

        return $body;
    }
}
