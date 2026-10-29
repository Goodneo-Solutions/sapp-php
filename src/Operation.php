<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** One operation of a generated API (docs/PLATFORM-APP-SDK-DESIGN.md §10.2). */
final class Operation
{
    /**
     * @param string $auth 'token', 'key', 'token_or_key' or 'none'
     * @param list<string> $query the query parameters of the operation
     * @param string|null $body 'json', 'form', 'multipart' or null
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $auth,
        public readonly array $query,
        public readonly ?string $body,
    ) {
    }
}
