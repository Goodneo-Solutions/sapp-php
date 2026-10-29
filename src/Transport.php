<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** Sends one HTTP request. Give the clients your own (a PSR-18 adapter, a test double) or use the default. */
interface Transport
{
    /**
     * @param array<string, string> $headers
     * @throws TransportException when the request got no answer
     */
    public function send(string $method, string $url, array $headers, ?string $body): Response;
}
