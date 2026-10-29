<?php

declare(strict_types=1);

namespace Tests;

use Superroute\SApp\Response;
use Superroute\SApp\Transport;
use Superroute\SApp\TransportException;

/** Answers from a list of handlers and records every request, like the JavaScript tests' fakeFetch. */
final class FakeTransport implements Transport
{
    /** @var list<array{method: string, path: string, query: array<string, mixed>, headers: array<string, string>, body: mixed, raw: ?string}> */
    public array $calls = [];

    /** @param list<array|callable> $handlers [status, json, headers] or a callable answering one; status 'network' fails */
    public function __construct(private array $handlers)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body): Response
    {
        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);
        $decoded = $body;
        if ($body !== null && ($headers['Content-Type'] ?? '') === 'application/x-www-form-urlencoded') {
            parse_str($body, $decoded);
        } elseif ($body !== null && ($headers['Content-Type'] ?? '') === 'application/json') {
            $decoded = json_decode($body, true);
        }
        $call = ['method' => $method, 'path' => $parts['path'] ?? '/', 'query' => $query, 'headers' => $headers, 'body' => $decoded, 'raw' => $body];
        $this->calls[] = $call;
        if ($this->handlers === []) {
            throw new \RuntimeException("unexpected request {$method} {$call['path']}");
        }
        $handler = array_shift($this->handlers);
        [$status, $json, $answerHeaders] = (is_callable($handler) ? $handler($call) : $handler) + [2 => []];
        if ($status === 'network') {
            throw new TransportException('fetch failed');
        }

        return new Response($status, array_change_key_case($answerHeaders, CASE_LOWER), $json === null ? '' : (string) json_encode($json));
    }
}
