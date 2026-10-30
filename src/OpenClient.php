<?php

declare(strict_types=1);

namespace Superroute\SApp;

use Superroute\SApp\Generated\OpenApi;

/**
 * The open profile: Open Platform capabilities (routing, geocoding, ETA, packing, …) at open.superroute.ca/v1.
 * On a server, pass the Open Platform API key; or pass getToken, answered with a short-lived token.
 *
 *     $open = new OpenClient(apiKey: getenv('SUPERROUTE_OPEN_API_KEY'));
 *     $route = $open->api->routingOptimize(body: [...]);
 */
final class OpenClient
{
    /** One method per Open Platform operation. */
    public readonly OpenApi $api;
    public readonly string $baseUrl;

    /** @var \Closure(Operation, array<string, mixed>, array<string, mixed>|null, list<OperationFile>): mixed */
    private readonly \Closure $call;

    /**
     * @param callable(): string|null $getToken asked for every call
     * @param callable(float): void|null $sleep seconds; the retry backoff
     */
    public function __construct(
        ?string $apiKey = null,
        ?callable $getToken = null,
        string $baseUrl = Http::DEFAULT_BASE_URL,
        ?string $locale = null,
        int $retries = 2,
        ?Transport $transport = null,
        ?callable $sleep = null,
    ) {
        if ($getToken === null && ($apiKey === null || $apiKey === '')) {
            throw new \InvalidArgumentException('getToken or apiKey is required');
        }
        $this->baseUrl = rtrim($baseUrl, '/');
        $transport ??= Http::defaultTransport();
        $token = $getToken !== null ? \Closure::fromCallable($getToken) : static fn (): string => (string) $apiKey;
        $sleep = $sleep !== null ? \Closure::fromCallable($sleep) : static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
        $this->call = function (Operation $operation, array $params, ?array $body, array $files) use ($token, $locale, $retries, $transport, $sleep): mixed {
            [$encoded, $bodyHeaders] = Http::encodeBody($operation->body, $body, $files);
            $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token(), 'X-SApp' => 'php/' . Http::SDK_VERSION] + $bodyHeaders;
            if ($locale !== null && $locale !== '') {
                $headers['Accept-Language'] = $locale;
            }
            $headers = Http::withCallHeaders($headers, Http::callHeaders($operation, $params));
            $response = Http::send($transport, $operation->method, Http::url($this->baseUrl, $operation, $params), $headers, $encoded, $retries, $operation->method === 'GET', $sleep);

            return Http::orThrow($response, true);
        };
        $this->api = new OpenApi($this->call);
    }

    /**
     * Building on the sApp core: calls an operation described by hand exactly as the generated methods of $api do (the
     * token, header parameters and idempotency keys, extra query, retries of GET calls and SuperrouteException for a
     * refusal).
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed>|null $body
     * @param list<OperationFile> $files
     */
    public function callOperation(Operation $operation, array $params = [], ?array $body = null, array $files = []): mixed
    {
        return ($this->call)($operation, $params, $body, $files);
    }
}
