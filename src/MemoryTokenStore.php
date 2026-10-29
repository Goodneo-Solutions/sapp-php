<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** Keeps the tokens for this request only. */
final class MemoryTokenStore implements TokenStore
{
    public function __construct(private ?Tokens $tokens = null)
    {
    }

    public function get(): ?Tokens
    {
        return $this->tokens;
    }

    public function set(Tokens $tokens): void
    {
        $this->tokens = $tokens;
    }

    public function clear(): void
    {
        $this->tokens = null;
    }
}
