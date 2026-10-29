<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** An HTTP answer as a Transport gives it. Header names are lower case. */
final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers = [],
        public readonly string $body = '',
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
