<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** The fallback transport over PHP streams, for servers without ext-curl. */
final class StreamTransport implements Transport
{
    public function __construct(private readonly float $timeoutSeconds = 30.0)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $lines = '';
        foreach ($headers as $name => $value) {
            $lines .= $name . ': ' . $value . "\r\n";
        }
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => $lines,
            'content' => $body ?? '',
            'ignore_errors' => true,
            'follow_location' => 0,
            'timeout' => $this->timeoutSeconds,
        ]]);
        $answer = @file_get_contents($url, false, $context);
        /** @var list<string> $http_response_header */
        $raw = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        if ($answer === false && $raw === []) {
            throw new TransportException('The request could not reach Superroute.');
        }
        $status = 0;
        $received = [];
        foreach ($raw as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m) === 1) {
                $status = (int) $m[1];
                $received = [];
                continue;
            }
            $parts = explode(':', $line, 2);
            if (count($parts) === 2) {
                $received[strtolower(trim($parts[0]))] = trim($parts[1]);
            }
        }

        return new Response($status, $received, $answer === false ? '' : $answer);
    }
}
