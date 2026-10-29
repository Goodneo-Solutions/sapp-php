<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** Where tokens are kept: on a server, the person's session or an encrypted database row. */
interface TokenStore
{
    public function get(): ?Tokens;

    public function set(Tokens $tokens): void;

    public function clear(): void;
}
