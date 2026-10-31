<?php

declare(strict_types=1);

namespace Superroute\SApp;

use Superroute\SApp\Generated\PartnerApi;

/**
 * The partner profile: an app registered on the Open Platform console, acting for one person at one service provider.
 *
 *     $sr = new PartnerClient(appCode: 'p_acme_parcels', clientCode: 'k3m9q2', appKey: 'sp_appkey_…', tokenStore: $store);
 *     $sr->signInWithIdToken($googleIdToken);
 *     $wallet = $sr->api->platformAppWallet();
 */
final class PartnerClient
{
    private const TOKEN_EXCHANGE = 'urn:ietf:params:oauth:grant-type:token-exchange';
    private const ID_TOKEN = 'urn:ietf:params:oauth:token-type:id_token';
    /** Refresh this long before the access token expires, so a call never starts with a token about to lapse. */
    private const REFRESH_MARGIN_MS = 60_000;

    /** One method per operation of the partner surface. */
    public readonly PartnerApi $api;
    public readonly string $baseUrl;
    private readonly Transport $transport;
    /** @var \Closure(): int milliseconds */
    private readonly \Closure $now;
    /** @var \Closure(float): void */
    private readonly \Closure $sleep;
    /** @var \Closure(): void|null */
    private readonly ?\Closure $onSignedOut;
    /** @var array<int, callable(bool): void> */
    private array $listeners = [];
    private int $nextListener = 0;
    private bool $refreshing = false;

    /**
     * @param callable(): int|null $now milliseconds since the epoch
     * @param callable(float): void|null $sleep seconds; the retry backoff
     * @param callable(): void|null $onSignedOut called when the session ends
     */
    public function __construct(
        public readonly string $appCode,
        private ?string $clientCode = null,
        public readonly ?string $appKey = null,
        public readonly TokenStore $tokenStore = new MemoryTokenStore(),
        string $baseUrl = Http::DEFAULT_BASE_URL,
        private ?string $locale = null,
        public readonly ?string $deviceId = null,
        public readonly int $retries = 2,
        ?Transport $transport = null,
        ?callable $now = null,
        ?callable $sleep = null,
        ?callable $onSignedOut = null,
    ) {
        if ($appCode === '') {
            throw new \InvalidArgumentException('appCode is required');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->transport = $transport ?? Http::defaultTransport();
        $this->now = $now !== null ? \Closure::fromCallable($now) : static fn (): int => (int) floor(microtime(true) * 1000);
        $this->sleep = $sleep !== null ? \Closure::fromCallable($sleep) : static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
        $this->onSignedOut = $onSignedOut !== null ? \Closure::fromCallable($onSignedOut) : null;
        $this->api = new PartnerApi(fn (Operation $operation, array $params, ?array $body, array $files): mixed => $this->callOperation($operation, $params, $body, $files));
    }

    /**
     * Building on the sApp core: calls an operation described by hand exactly as the generated methods of $api do —
     * the same sign-in headers (the token, or the app key when the operation allows it), one refresh and retry after a
     * 401, an Idempotency-Key made once per call (and kept across that retry) when the operation declares one and none
     * is given, retries of GET calls, and SuperrouteException for a refusal (status 400 or more, or "result": false).
     * Path and query values, header parameters (under the header's own name) and the extra query (under
     * Operation::EXTRA_QUERY) travel in $params; {clientCode} is filled from the client when not given. For packages
     * such as sLocker that add their own operations on top of this client.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed>|null $body
     * @param list<OperationFile> $files
     */
    public function callOperation(Operation $operation, array $params = [], ?array $body = null, array $files = []): mixed
    {
        // Public calls name the service provider in the path; take it from the client unless the caller passes one.
        if (str_contains($operation->path, '{clientCode}') && ($params['clientCode'] ?? null) === null && $this->clientCode !== null) {
            $params['clientCode'] = $this->clientCode;
        }

        return $this->call($operation, $params, $body, $files);
    }

    public function clientCode(): ?string
    {
        return $this->clientCode;
    }

    /** Change the service provider, e.g. after the person picked one at sign-up. Sign in again afterwards. */
    public function setClientCode(?string $clientCode): void
    {
        $this->clientCode = $clientCode;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    /**
     * Called with true when a sign-in starts a session and false when it ends. Answers the function that stops it.
     *
     * @param callable(bool): void $listener
     * @return callable(): void
     */
    public function onSessionChange(callable $listener): callable
    {
        $id = $this->nextListener++;
        $this->listeners[$id] = $listener;

        return function () use ($id): void {
            unset($this->listeners[$id]);
        };
    }

    private function emit(bool $signedIn): void
    {
        foreach ($this->listeners as $listener) {
            try {
                $listener($signedIn);
            } catch (\Throwable) {
                // A listener's error never breaks the session.
            }
        }
    }

    /** @return array<string, string> */
    private function baseHeaders(): array
    {
        $headers = ['Accept' => 'application/json', 'X-SApp' => 'php/' . Http::SDK_VERSION];
        if ($this->locale !== null && $this->locale !== '') {
            $headers['Accept-Language'] = $this->locale;
        }

        return $headers;
    }

    private function requireClientCode(): string
    {
        if ($this->clientCode === null || $this->clientCode === '') {
            throw new \LogicException('clientCode is required to sign in');
        }

        return $this->clientCode;
    }

    /** @param array<string, string|null> $fields */
    private function tokenRequest(array $fields): Tokens
    {
        $fields = array_filter(['client_id' => $this->appCode] + $fields, static fn ($v) => $v !== null && $v !== '');
        $response = Http::send(
            $this->transport, 'POST', $this->baseUrl . '/app/oauth/token',
            $this->baseHeaders() + ['Content-Type' => 'application/x-www-form-urlencoded'], Http::form($fields), 0, false, $this->sleep,
        );
        $body = Http::orThrow($response);
        if (!is_array($body) || !is_string($body['access_token'] ?? null)) {
            throw SuperrouteException::withAnswer($response->status, 'invalid_response', 'The token answer had no access token.', $body, $response->headers);
        }
        $expiresIn = is_numeric($body['expires_in'] ?? null) && (float) $body['expires_in'] > 0 ? (float) $body['expires_in'] : 3600.0;
        $tokens = new Tokens(
            $body['access_token'],
            is_string($body['refresh_token'] ?? null) && $body['refresh_token'] !== '' ? $body['refresh_token'] : null,
            ($this->now)() + (int) ($expiresIn * 1000),
            is_string($body['scope'] ?? null) ? array_values(array_filter(explode(' ', $body['scope']), static fn ($s) => $s !== '')) : [],
        );
        $wasSignedIn = $this->tokenStore->get() !== null;
        $this->tokenStore->set($tokens);
        if (!$wasSignedIn) {
            $this->emit(true);
        }

        return $tokens;
    }

    private function endSession(): void
    {
        $this->tokenStore->clear();
        if ($this->onSignedOut !== null) {
            ($this->onSignedOut)();
        }
        $this->emit(false);
    }

    /**
     * The Superroute sign-in page (method 1): redirect the person to the request's URL, keep the request (verifier and
     * state) in the session, and pass the URL the person came back to to completeSignIn().
     *
     * @param list<string> $scope
     */
    public function authorizationRequest(string $redirectUri, array $scope = [], ?string $state = null, ?string $language = null): AuthorizationRequest
    {
        $pkce = Pkce::create();
        $state ??= Pkce::base64Url(random_bytes(16));
        $query = array_filter([
            'response_type' => 'code', 'client_id' => $this->appCode, 'client_code' => $this->requireClientCode(),
            'redirect_uri' => $redirectUri, 'state' => $state, 'code_challenge' => $pkce['challenge'],
            'code_challenge_method' => 'S256', 'scope' => implode(' ', $scope), 'language' => $language ?? $this->locale,
        ], static fn ($v) => $v !== null && $v !== '');

        return new AuthorizationRequest($this->baseUrl . '/app/oauth/authorize?' . Http::form($query), $pkce['verifier'], $state, $redirectUri);
    }

    /** Finishes method 1 with the URL the sign-in page returned to. Throws when it does not belong to the request. */
    public function completeSignIn(AuthorizationRequest $request, string $returnedUrl): Tokens
    {
        parse_str((string) parse_url($returnedUrl, PHP_URL_QUERY), $answer);
        if (($answer['state'] ?? null) !== $request->state) {
            throw new SuperrouteException(400, 'invalid_state', 'The sign-in answer does not belong to this request.');
        }
        if (is_string($answer['error'] ?? null) && $answer['error'] !== '') {
            throw new SuperrouteException(400, $answer['error'], is_string($answer['error_description'] ?? null) ? $answer['error_description'] : null);
        }

        return $this->exchangeCode((string) ($answer['code'] ?? ''), $request->redirectUri, $request->verifier);
    }

    /** Finish method 1 with the redirect's code and the verifier of authorizationRequest(). */
    public function exchangeCode(string $code, string $redirectUri, string $verifier): Tokens
    {
        return $this->tokenRequest([
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $redirectUri,
            'code_verifier' => $verifier, 'device_id' => $this->deviceId,
        ]);
    }

    /**
     * Method 2: a Google or Apple ID token issued for one of the app's registered audiences.
     *
     * @param list<string> $scope
     */
    public function signInWithIdToken(string $idToken, array $scope = []): Tokens
    {
        return $this->tokenRequest([
            'grant_type' => self::TOKEN_EXCHANGE, 'client_code' => $this->requireClientCode(), 'subject_token' => $idToken,
            'subject_token_type' => self::ID_TOKEN, 'scope' => implode(' ', $scope), 'device_id' => $this->deviceId,
        ]);
    }

    /**
     * Method 3: email and password in the app's own screens, for apps approved for it.
     *
     * @param list<string> $scope
     */
    public function signInWithPassword(string $username, string $password, array $scope = []): Tokens
    {
        return $this->tokenRequest([
            'grant_type' => 'password', 'client_code' => $this->requireClientCode(), 'username' => $username,
            'password' => $password, 'scope' => implode(' ', $scope), 'device_id' => $this->deviceId,
        ]);
    }

    /** Swaps the refresh token for new tokens (a refresh token works once). A refused refresh ends the session. */
    public function refresh(): Tokens
    {
        if ($this->refreshing) {
            throw new \LogicException('A refresh is already running.');
        }
        $this->refreshing = true;
        try {
            $tokens = $this->tokenStore->get();
            if ($tokens === null || $tokens->refreshToken === null) {
                $this->endSession();
                throw new SuperrouteException(401, 'invalid_grant', 'Not signed in.');
            }
            try {
                return $this->tokenRequest(['grant_type' => 'refresh_token', 'refresh_token' => $tokens->refreshToken, 'device_id' => $this->deviceId]);
            } catch (SuperrouteException $e) {
                if ($e->errorCode === 'invalid_grant' || $e->status === 401) {
                    $this->endSession();
                }
                throw $e;
            }
        } finally {
            $this->refreshing = false;
        }
    }

    private function accessToken(): ?string
    {
        $tokens = $this->tokenStore->get();
        if ($tokens === null) {
            return null;
        }
        if ($tokens->refreshToken !== null && $tokens->expiresAt - self::REFRESH_MARGIN_MS <= ($this->now)()) {
            return $this->refresh()->accessToken;
        }

        return $tokens->accessToken;
    }

    /** Ends this sign-in on the server and forgets the tokens. */
    public function signOut(): void
    {
        $tokens = $this->tokenStore->get();
        if ($tokens !== null && $tokens->refreshToken !== null) {
            try {
                Http::send($this->transport, 'POST', $this->baseUrl . '/app/oauth/revoke',
                    $this->baseHeaders() + ['Content-Type' => 'application/x-www-form-urlencoded'],
                    Http::form(['token' => $tokens->refreshToken, 'client_id' => $this->appCode]), 0, false, $this->sleep);
            } catch (\Throwable) {
                // Signing out locally never waits on the network.
            }
        }
        $this->endSession();
    }

    public function isSignedIn(): bool
    {
        return $this->tokenStore->get() !== null;
    }

    /** @return array<string, string> */
    private function authorise(Operation $operation, array $headers): array
    {
        if ($operation->auth === 'token' || $operation->auth === 'token_or_key') {
            $token = $this->accessToken();
            if ($token !== null) {
                $headers['Authorization'] = 'Bearer ' . $token;

                return $headers;
            }
            if ($operation->auth === 'token') {
                throw new SuperrouteException(401, 'invalid_token', 'Not signed in.');
            }
        }
        if (($operation->auth === 'key' || $operation->auth === 'token_or_key') && $this->appKey !== null) {
            $headers['X-App-Key'] = $this->appKey;
        }

        return $headers;
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed>|null $body
     * @param list<OperationFile> $files
     */
    private function call(Operation $operation, array $params, ?array $body, array $files): mixed
    {
        return Http::orThrow($this->exchange($operation, $params, $body, $files), true);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed>|null $body
     * @param list<OperationFile> $files
     */
    private function exchange(Operation $operation, array $params, ?array $body, array $files): Response
    {
        $url = Http::url($this->baseUrl, $operation, $params);
        [$encoded, $bodyHeaders] = Http::encodeBody($operation->body, $body, $files);
        $retryable = $operation->method === 'GET';
        $perCall = Http::callHeaders($operation, $params);
        $headers = Http::withCallHeaders($this->authorise($operation, $this->baseHeaders() + $bodyHeaders), $perCall);
        $response = Http::send($this->transport, $operation->method, $url, $headers, $encoded, $this->retries, $retryable, $this->sleep);
        // An access token can end early (revoked, the person's access changed): refresh once and try again.
        if ($response->status === 401 && isset($headers['Authorization'])) {
            $this->refresh();
            $headers = Http::withCallHeaders($this->authorise($operation, $this->baseHeaders() + $bodyHeaders), $perCall);
            $response = Http::send($this->transport, $operation->method, $url, $headers, $encoded, $this->retries, $retryable, $this->sleep);
        }

        return $response;
    }

    /**
     * A partner GraphQL operation (open.superroute.ca/app/graphql), with the token when signed in, else the app key.
     *
     * @param array<string, mixed> $variables
     */
    public function graphql(string $query, array $variables = []): mixed
    {
        $response = $this->exchange(
            new Operation('POST', '/app/graphql', 'token_or_key', [], 'json'),
            [],
            ['query' => $query, 'variables' => $variables === [] ? new \stdClass() : $variables],
            [],
        );
        $answer = Http::orThrow($response, true);
        if (is_array($answer) && is_array($answer['errors'] ?? null) && $answer['errors'] !== []) {
            $messages = array_map(static fn ($e) => is_array($e) && is_string($e['message'] ?? null) ? $e['message'] : '', $answer['errors']);
            throw SuperrouteException::withAnswer(200, 'graphql_error', implode(' ', $messages), $answer, $response->headers);
        }

        return is_array($answer) ? ($answer['data'] ?? null) : null;
    }
}
