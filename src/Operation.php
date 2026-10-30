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
     * @param list<string> $headers the header parameters of the operation; their values travel in the parameter map
     *                              under the header's own name
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly string $auth,
        public readonly array $query,
        public readonly ?string $body,
        public readonly array $headers = [],
    ) {
    }

    /** The key of the parameter map that carries a call's extra query (names the spec does not declare). */
    public const EXTRA_QUERY = 'extraQuery';
}
