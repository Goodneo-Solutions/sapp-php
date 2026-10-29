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
        $this->api = new OpenApi(function (Operation $operation, array $params, ?array $body, array $files) use ($token, $locale, $retries, $transport, $sleep) {
            [$encoded, $bodyHeaders] = Http::encodeBody($operation->body, $body, $files);
            $headers = ['Accept' => 'application/json', 'Authorization' => 'Bearer ' . $token(), 'X-SApp' => 'php/' . Http::SDK_VERSION] + $bodyHeaders;
            if ($locale !== null && $locale !== '') {
                $headers['Accept-Language'] = $locale;
            }
            $response = Http::send($transport, $operation->method, Http::url($this->baseUrl, $operation, $params), $headers, $encoded, $retries, $operation->method === 'GET', $sleep);

            return Http::orThrow($response);
        });
    }
}
