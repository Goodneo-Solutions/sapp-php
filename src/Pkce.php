<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** PKCE (RFC 7636) with S256. */
final class Pkce
{
    public static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** S256 code challenge of a verifier (RFC 7636 §4.2). */
    public static function codeChallenge(string $verifier): string
    {
        return self::base64Url(hash('sha256', $verifier, true));
    }

    /** @return array{verifier: string, challenge: string, method: string} a fresh pair */
    public static function create(): array
    {
        $verifier = self::base64Url(random_bytes(48));

        return ['verifier' => $verifier, 'challenge' => self::codeChallenge($verifier), 'method' => 'S256'];
    }
}
