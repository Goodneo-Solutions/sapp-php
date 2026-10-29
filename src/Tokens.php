<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** A session's tokens. `expiresAt` is in milliseconds since the epoch, as in every other sApp language. */
final class Tokens
{
    /** @param list<string> $scope */
    public function __construct(
        public readonly string $accessToken,
        public readonly ?string $refreshToken = null,
        public readonly int $expiresAt = 0,
        public readonly array $scope = [],
    ) {
    }

    /** @return array{accessToken: string, refreshToken: ?string, expiresAt: int, scope: list<string>} for storage */
    public function toArray(): array
    {
        return ['accessToken' => $this->accessToken, 'refreshToken' => $this->refreshToken, 'expiresAt' => $this->expiresAt, 'scope' => $this->scope];
    }

    /** @param array<string, mixed> $data what toArray() answered */
    public static function fromArray(array $data): self
    {
        return new self(
            (string) ($data['accessToken'] ?? ''),
            isset($data['refreshToken']) ? (string) $data['refreshToken'] : null,
            (int) ($data['expiresAt'] ?? 0),
            array_values(array_map('strval', (array) ($data['scope'] ?? []))),
        );
    }
}
