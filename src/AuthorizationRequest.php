<?php

declare(strict_types=1);

namespace Superroute\SApp;

/** A started sign-in on the Superroute sign-in page. Keep it (e.g. in the session) until the redirect comes back. */
final class AuthorizationRequest
{
    public function __construct(
        public readonly string $url,
        public readonly string $verifier,
        public readonly string $state,
        public readonly string $redirectUri,
    ) {
    }
}
