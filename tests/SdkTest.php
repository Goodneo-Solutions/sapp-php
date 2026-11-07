<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Superroute\SApp\Generated\OpenApi;
use Superroute\SApp\Generated\PartnerApi;
use Superroute\SApp\MemoryTokenStore;
use Superroute\SApp\OpenClient;
use Superroute\SApp\Operation;
use Superroute\SApp\PartnerClient;
use Superroute\SApp\Pkce;
use Superroute\SApp\SuperrouteException;
use Superroute\SApp\Theme;
use Superroute\SApp\Tokens;

/** The shared cases of every sApp language (js/test/sdk.test.mjs). */
final class SdkTest extends TestCase
{
    private static function tokenBody(int $n): array
    {
        return ['access_token' => "sp_app_access{$n}", 'refresh_token' => "sp_app_refresh{$n}", 'token_type' => 'Bearer', 'expires_in' => 3600, 'scope' => 'tracking notifications.in_app'];
    }

    private static function nowMs(): int
    {
        return (int) floor(microtime(true) * 1000);
    }

    private static function signedIn(int $seconds = 3600, string $access = 'old', string $refresh = 'r1'): MemoryTokenStore
    {
        return new MemoryTokenStore(new Tokens($access, $refresh, self::nowMs() + $seconds * 1000));
    }

    private static function client(FakeTransport $transport, array $options = []): PartnerClient
    {
        return new PartnerClient(...($options + ['appCode' => 'p_a', 'clientCode' => 'c', 'transport' => $transport, 'sleep' => static function (float $s): void {
        }]));
    }

    private static function failure(callable $block): SuperrouteException
    {
        try {
            $block();
        } catch (SuperrouteException $e) {
            return $e;
        }
        self::fail('expected a SuperrouteException');
    }

    public function testPkceChallengeIsS256OfTheVerifier(): void
    {
        self::assertSame('E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM', Pkce::codeChallenge('dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'));
        $pair = Pkce::create();
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43,128}$/', $pair['verifier']);
        self::assertSame(Pkce::codeChallenge($pair['verifier']), $pair['challenge']);
    }

    public function testSignInPageUrlAndCodeExchange(): void
    {
        $t = new FakeTransport([[200, self::tokenBody(1)]]);
        $sr = self::client($t, ['appCode' => 'p_acme_parcels', 'clientCode' => 'k3m9q2', 'locale' => 'fr']);
        $request = $sr->authorizationRequest('com.acme.parcels:/oauth', ['wallet.top_up']);
        $u = parse_url($request->url);
        parse_str($u['query'], $q);
        self::assertSame('https://open.superroute.ca/app/oauth/authorize', "{$u['scheme']}://{$u['host']}{$u['path']}");
        self::assertSame('p_acme_parcels', $q['client_id']);
        self::assertSame('k3m9q2', $q['client_code']);
        self::assertSame(Pkce::codeChallenge($request->verifier), $q['code_challenge']);
        self::assertSame('S256', $q['code_challenge_method']);
        self::assertSame($request->state, $q['state']);
        self::assertSame('wallet.top_up', $q['scope']);
        self::assertSame('fr', $q['language']);

        $tokens = $sr->exchangeCode('abc', 'com.acme.parcels:/oauth', $request->verifier);
        self::assertSame('sp_app_access1', $tokens->accessToken);
        self::assertSame(['tracking', 'notifications.in_app'], $tokens->scope);
        self::assertSame(['client_id' => 'p_acme_parcels', 'grant_type' => 'authorization_code', 'code' => 'abc', 'redirect_uri' => 'com.acme.parcels:/oauth', 'code_verifier' => $request->verifier], $t->calls[0]['body']);
    }

    public function testIdTokenAndPasswordGrants(): void
    {
        $t = new FakeTransport([[200, self::tokenBody(1)], [200, self::tokenBody(2)]]);
        $sr = self::client($t, ['appCode' => 'p_acme_parcels', 'clientCode' => 'k3m9q2', 'deviceId' => 'ios-1']);
        $sr->signInWithIdToken('eyJ.google.token');
        $sr->signInWithPassword('a@example.com', 'secret', ['wallet.top_up']);
        self::assertSame([
            'client_id' => 'p_acme_parcels', 'grant_type' => 'urn:ietf:params:oauth:grant-type:token-exchange', 'client_code' => 'k3m9q2',
            'subject_token' => 'eyJ.google.token', 'subject_token_type' => 'urn:ietf:params:oauth:token-type:id_token', 'device_id' => 'ios-1',
        ], $t->calls[0]['body']);
        self::assertSame(['client_id' => 'p_acme_parcels', 'grant_type' => 'password', 'client_code' => 'k3m9q2', 'username' => 'a@example.com', 'password' => 'secret', 'scope' => 'wallet.top_up', 'device_id' => 'ios-1'], $t->calls[1]['body']);
    }

    public function testARefusedSignInCarriesTheServerCode(): void
    {
        $t = new FakeTransport([[400, ['error' => 'interaction_required', 'error_description' => 'This account uses two-step verification.']]]);
        $e = self::failure(fn () => self::client($t)->signInWithPassword('a', 'b'));
        self::assertSame('interaction_required', $e->errorCode);
        self::assertSame(400, $e->status);
        self::assertStringContainsString('two-step', $e->getMessage());
    }

    public function testCallsCarryTokenPathAndQuery(): void
    {
        $t = new FakeTransport([[200, ['result' => true]], [200, ['result' => true]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn(access: 'sp_app_x', refresh: 'r'), 'locale' => 'de']);
        $sr->api->platformAppMarkNotificationRead(notificationId: '0f8fad5b-d9cb-469f-a165-70867728950e');
        self::assertSame('POST', $t->calls[0]['method']);
        self::assertSame('/app/v1/notifications/0f8fad5b-d9cb-469f-a165-70867728950e/read', $t->calls[0]['path']);
        self::assertSame('Bearer sp_app_x', $t->calls[0]['headers']['Authorization']);
        self::assertSame('de', $t->calls[0]['headers']['Accept-Language']);
        self::assertSame('php/0.2.6', $t->calls[0]['headers']['X-SApp']);

        // Booleans in the query are sent as 1 / 0.
        $sr->api->platformAppStoreCategory('shop', 3, inStock: true);
        self::assertSame('/app/v1/stores/shop/categories/3', $t->calls[1]['path']);
        self::assertSame(['in_stock' => '1'], $t->calls[1]['query']);
    }

    public function testPublicCallsSendTheAppKeyAndTheClientCode(): void
    {
        $t = new FakeTransport([[200, ['result' => true]]]);
        $sr = self::client($t, ['clientCode' => 'k3m9q2', 'appKey' => 'sp_appkey_abc']);
        $sr->api->platformAppBootstrap(platform: 'ios');
        self::assertSame('/app/v1/clients/k3m9q2/bootstrap', $t->calls[0]['path']);
        self::assertSame(['platform' => 'ios'], $t->calls[0]['query']);
        self::assertSame('sp_appkey_abc', $t->calls[0]['headers']['X-App-Key']);
        self::assertArrayNotHasKey('Authorization', $t->calls[0]['headers']);
    }

    public function testACallWithoutASessionIsRefusedBeforeAnyRequest(): void
    {
        $t = new FakeTransport([]);
        $e = self::failure(fn () => self::client($t)->api->platformAppNotifications());
        self::assertSame('invalid_token', $e->errorCode);
        self::assertTrue($e->signInRequired());
        self::assertCount(0, $t->calls);
    }

    public function testA401RefreshesOnceAndRepeatsTheCall(): void
    {
        $store = self::signedIn();
        $refreshes = 0;
        $handler = static function (array $call) use (&$refreshes): array {
            if ($call['path'] === '/app/oauth/token') {
                $refreshes++;
                return [200, self::tokenBody(2)];
            }

            return ($call['headers']['Authorization'] ?? '') === 'Bearer sp_app_access2' ? [200, ['result' => true]] : [401, ['error' => 'invalid_token']];
        };
        $t = new FakeTransport(array_fill(0, 6, $handler));
        $sr = self::client($t, ['tokenStore' => $store]);
        self::assertSame(['result' => true], $sr->api->platformAppNotifications());
        self::assertSame(['result' => true], $sr->api->platformAppNotifications());
        self::assertSame(1, $refreshes);
        self::assertSame('sp_app_refresh2', $store->get()?->refreshToken);
        $refresh = array_values(array_filter($t->calls, static fn ($c) => $c['path'] === '/app/oauth/token'))[0];
        self::assertSame(['client_id' => 'p_a', 'grant_type' => 'refresh_token', 'refresh_token' => 'r1'], $refresh['body']);
    }

    public function testATokenAboutToExpireIsRefreshedBeforeTheCall(): void
    {
        $t = new FakeTransport([[200, self::tokenBody(3)], [200, ['result' => true]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn(10)]);
        $sr->api->platformAppNotifications();
        self::assertSame('/app/oauth/token', $t->calls[0]['path']);
        self::assertSame('Bearer sp_app_access3', $t->calls[1]['headers']['Authorization']);
    }

    public function testARefusedRefreshEndsTheSessionAndTellsTheApp(): void
    {
        $store = self::signedIn();
        $signedOut = 0;
        $t = new FakeTransport([[401, ['error' => 'invalid_token']], [400, ['error' => 'invalid_grant']]]);
        $sr = self::client($t, ['tokenStore' => $store, 'onSignedOut' => static function () use (&$signedOut): void {
            $signedOut++;
        }]);
        $e = self::failure(fn () => $sr->api->platformAppNotifications());
        self::assertSame('invalid_grant', $e->errorCode);
        self::assertTrue($e->signInRequired());
        self::assertNull($store->get());
        self::assertSame(1, $signedOut);
    }

    public function testSignOutRevokesAndForgets(): void
    {
        $t = new FakeTransport([[200, []]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        $sr->signOut();
        self::assertSame('/app/oauth/revoke', $t->calls[0]['path']);
        self::assertSame(['token' => 'r1', 'client_id' => 'p_a'], $t->calls[0]['body']);
        self::assertFalse($sr->isSignedIn());
    }

    public function testRepeatableCallsAreRetriedAndWritesAreNot(): void
    {
        $t = new FakeTransport([[503, null], ['network', null], [200, ['result' => true]], [503, ['message' => 'Down']]]);
        $sleeps = [];
        $sr = self::client($t, ['tokenStore' => self::signedIn(), 'retries' => 2, 'sleep' => static function (float $s) use (&$sleeps): void {
            $sleeps[] = $s;
        }]);
        self::assertSame(['result' => true], $sr->api->platformAppNotifications());
        self::assertCount(3, $t->calls);
        self::assertSame([0.3, 0.6], $sleeps);
        $e = self::failure(fn () => $sr->api->platformAppMarkAllNotificationsRead());
        self::assertSame(503, $e->status);
        self::assertCount(4, $t->calls);
    }

    public function testRetryAfterIsHonouredAndCapped(): void
    {
        $t = new FakeTransport([[429, null, ['Retry-After' => '120']], [200, ['result' => true]]]);
        $sleeps = [];
        $sr = self::client($t, ['tokenStore' => self::signedIn(), 'sleep' => static function (float $s) use (&$sleeps): void {
            $sleeps[] = $s;
        }]);
        $sr->api->platformAppNotifications();
        self::assertEquals([30], $sleeps);
    }

    public function testPlatformAppErrorCodesReachTheCaller(): void
    {
        $t = new FakeTransport([[403, ['result' => false, 'message' => 'APP_ACCESS_DENIED'], ['X-Request-Id' => 'req-1']]]);
        $e = self::failure(fn () => self::client($t, ['tokenStore' => self::signedIn()])->api->platformAppNotifications());
        self::assertSame('APP_ACCESS_DENIED', $e->errorCode);
        self::assertSame('req-1', $e->requestId);
    }

    public function testGraphqlSendsTheQueryAndSurfacesErrors(): void
    {
        $t = new FakeTransport([[200, ['data' => ['partnerAppMe' => ['app_code' => 'p_a']]]], [200, ['errors' => [['message' => 'Unauthorized']]]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        self::assertSame(['partnerAppMe' => ['app_code' => 'p_a']], $sr->graphql('{ partnerAppMe { app_code } }'));
        self::assertSame('/app/graphql', $t->calls[0]['path']);
        self::assertSame('{"query":"{ partnerAppMe { app_code } }","variables":{}}', $t->calls[0]['raw']);
        $e = self::failure(fn () => $sr->graphql('{ x }'));
        self::assertSame('graphql_error', $e->errorCode);
        self::assertSame('Unauthorized', $e->getMessage());
    }

    public function testTheOpenClientAsksGetTokenForEveryCall(): void
    {
        $asked = 0;
        $t = new FakeTransport([[200, ['ok' => true]], [200, ['ok' => true]]]);
        $open = new OpenClient(getToken: static function () use (&$asked): string {
            return 'tok' . ++$asked;
        }, transport: $t);
        $id = null;
        foreach (OpenApi::operations() as $opId => $op) {
            if ($op->method === 'GET' && !str_contains($op->path, '{')) {
                $id = $opId;
                break;
            }
        }
        $open->api->{$id}();
        $open->api->{$id}();
        self::assertSame(OpenApi::operations()[$id]->path, $t->calls[0]['path']);
        self::assertSame('Bearer tok1', $t->calls[0]['headers']['Authorization']);
        self::assertSame('Bearer tok2', $t->calls[1]['headers']['Authorization']);
    }

    public function testTheOpenClientTakesAnApiKeyOnAServer(): void
    {
        $t = new FakeTransport([[200, ['ok' => true]]]);
        $open = new OpenClient(apiKey: 'sk_open_1', transport: $t);
        $open->api->routingOptimize(body: ['vehicles' => []]);
        self::assertSame('Bearer sk_open_1', $t->calls[0]['headers']['Authorization']);
        self::assertSame('{"vehicles":[]}', $t->calls[0]['raw']);
        $this->expectException(\InvalidArgumentException::class);
        new OpenClient();
    }

    public function testThemeTokensFollowTheBranding(): void
    {
        $dark = Theme::fromBranding(['primary_color' => '#1a3d7c', 'display_name' => 'Acme']);
        self::assertSame('#1A3D7C', $dark['color']['primary']);
        self::assertSame('#FFFFFF', $dark['color']['onPrimary']);
        self::assertSame('Acme', $dark['displayName']);
        self::assertSame('#111111', Theme::fromBranding(['primary_color' => '#FFE066'])['color']['onPrimary']);
        self::assertSame('#E02020', Theme::fromBranding(['primary_color' => 'red'])['color']['primary']);
    }

    public function testSubscribersHearTheSessionStartAndEnd(): void
    {
        $t = new FakeTransport([[200, self::tokenBody(1)], [200, self::tokenBody(2)], [200, []]]);
        $sr = self::client($t);
        $heard = [];
        $off = $sr->onSessionChange(static function (bool $signedIn) use (&$heard): void {
            $heard[] = $signedIn;
        });
        $sr->signInWithIdToken('t');
        $sr->refresh();
        $sr->signOut();
        $off();
        $sr->signOut();
        self::assertSame([true, false], $heard);
    }

    public function testCompleteSignInChecksTheStateAndTheError(): void
    {
        $t = new FakeTransport([[200, self::tokenBody(1)]]);
        $sr = self::client($t);
        $redirect = 'com.acme.parcels:/oauth';
        $request = $sr->authorizationRequest($redirect);
        self::assertSame('sp_app_access1', $sr->completeSignIn($request, "{$redirect}?code=xyz&state={$request->state}")->accessToken);
        self::assertSame('xyz', $t->calls[0]['body']['code']);
        self::assertSame('invalid_state', self::failure(fn () => $sr->completeSignIn($request, "{$redirect}?code=xyz&state=forged"))->errorCode);
        self::assertSame('access_denied', self::failure(fn () => $sr->completeSignIn($request, "{$redirect}?error=access_denied&state={$request->state}"))->errorCode);
    }

    public function testMultipartSendsFieldsAndFiles(): void
    {
        $t = new FakeTransport([[200, ['result' => true]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        foreach (PartnerApi::operations() as $id => $op) {
            if ($op->body === 'multipart') {
                $method = new \ReflectionMethod(PartnerApi::class, $id);
                $args = [];
                foreach ($method->getParameters() as $p) {
                    if (!$p->isOptional()) {
                        $args[$p->getName()] = $p->getName() === 'body' ? [] : '7';
                    }
                }
                $args['body'] = ['note' => 'hi', 'tags' => ['a', 'b']];
                $args['files'] = [new \Superroute\SApp\OperationFile('photo', 'PNGDATA', 'p.png', 'image/png')];
                $sr->api->{$id}(...$args);
                $raw = $t->calls[0]['raw'];
                self::assertStringStartsWith('multipart/form-data; boundary=', $t->calls[0]['headers']['Content-Type']);
                self::assertStringContainsString('name="tags[]"', $raw);
                self::assertStringContainsString("name=\"photo\"; filename=\"p.png\"\r\nContent-Type: image/png\r\n\r\nPNGDATA", $raw);
                return;
            }
        }
        self::markTestSkipped('no multipart operation in the spec');
    }

    private const UUID = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    public function testHeaderParametersAreSentAndNeverReplaceAuthorizationOrTheAppKey(): void
    {
        $t = new FakeTransport([[200, ['result' => true]], [200, ['result' => true]]]);
        $sr = self::client($t, ['clientCode' => 'k3m9q2', 'appKey' => 'sp_appkey_1', 'tokenStore' => self::signedIn()]);
        $sr->api->platformAppGuestStoreCart('shop', guestCartToken: 'cart-1');
        $this->assertSame('/app/v1/clients/k3m9q2/guest/stores/shop/cart', $t->calls[0]['path']);
        $this->assertSame('cart-1', $t->calls[0]['headers']['X-Guest-Cart-Token']);
        $this->assertSame('sp_appkey_1', $t->calls[0]['headers']['X-App-Key']);
        $this->assertSame([], $t->calls[0]['query']);
        $sr->api->platformAppWalletCreateTopUp(['amount' => 10], idempotencyKey: 'key-1');
        $this->assertSame('key-1', $t->calls[1]['headers']['Idempotency-Key']);
        $this->assertSame('Bearer old', $t->calls[1]['headers']['Authorization']);
        $this->assertStringStartsWith('php/', $t->calls[1]['headers']['X-SApp']);
    }

    public function testAnIdempotencyKeyIsMadePerCallAndKeptWhenTheCallIsSentAgain(): void
    {
        $t = new FakeTransport([[401, ['error' => 'invalid_token']], [200, self::tokenBody(2)], [200, ['result' => true]], [200, ['result' => true]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        $sr->api->platformAppWalletCreateTopUp(['amount' => 10]);
        $key = $t->calls[0]['headers']['Idempotency-Key'];
        $this->assertMatchesRegularExpression(self::UUID, $key);
        $this->assertSame($key, $t->calls[2]['headers']['Idempotency-Key']);
        $this->assertSame('Bearer sp_app_access2', $t->calls[2]['headers']['Authorization']);
        $this->assertArrayNotHasKey('Idempotency-Key', $t->calls[1]['headers']);
        $sr->api->platformAppWalletCreateTopUp(['amount' => 10]);
        $this->assertNotSame($key, $t->calls[3]['headers']['Idempotency-Key']);
    }

    public function testAHandMadeOperationThroughCallOperationBehavesLikeAGeneratedOne(): void
    {
        // A copy of the generated top-up operation, and an operation the generated API does not have.
        $topUp = new Operation('POST', '/app/v1/wallet/top-ups', 'token', [], 'json', ['Idempotency-Key']);
        $handMade = new Operation('GET', '/app/v1/clients/{clientCode}/guest/things/{id}', 'token_or_key', ['status'], null);
        $t = new FakeTransport([
            [200, ['result' => true]],
            [401, ['error' => 'invalid_token']],
            [200, self::tokenBody(2)],
            [200, ['result' => true, 'data' => 7]],
            [200, ['result' => true]],
            [200, ['result' => false, 'code' => 'NOPE', 'message' => 'Refused.']],
        ]);
        $sr = self::client($t, ['tokenStore' => self::signedIn(), 'appKey' => 'sp_appkey_1']);
        $sr->api->platformAppWalletCreateTopUp(['amount' => 10], idempotencyKey: 'key-1');
        $this->assertSame(['result' => true, 'data' => 7], $sr->callOperation($topUp, ['extraQuery' => ['x' => 1]], ['amount' => 10]));
        [$generated, $first, , $retried] = $t->calls;
        $this->assertSame([$generated['method'], $generated['path'], $generated['raw']], [$first['method'], $first['path'], $first['raw']]);
        $this->assertSame(['x' => '1'], $first['query']);
        $this->assertSame('Bearer old', $first['headers']['Authorization']);
        $this->assertSame('Bearer sp_app_access2', $retried['headers']['Authorization']);
        $this->assertMatchesRegularExpression(self::UUID, $first['headers']['Idempotency-Key']);
        $this->assertSame($first['headers']['Idempotency-Key'], $retried['headers']['Idempotency-Key']);
        $sr->callOperation($handMade, ['id' => 5, 'status' => 'open']);
        $this->assertSame('/app/v1/clients/c/guest/things/5', $t->calls[4]['path']);
        $this->assertSame(['status' => 'open'], $t->calls[4]['query']);
        $e = self::failure(fn () => $sr->callOperation($handMade, ['id' => 5]));
        $this->assertSame([200, 'NOPE'], [$e->status, $e->errorCode]);
    }

    public function testTheOpenClientCallOperationGoesThroughTheSameCall(): void
    {
        $t = new FakeTransport([[200, ['ok' => true]]]);
        $open = new OpenClient(apiKey: 'sk_open', transport: $t);
        $this->assertSame(['ok' => true], $open->callOperation(new Operation('GET', '/v1/capabilities', 'token', [], null), ['extraQuery' => ['a' => 'b']]));
        $this->assertSame(['/v1/capabilities', ['a' => 'b'], 'Bearer sk_open'], [$t->calls[0]['path'], $t->calls[0]['query'], $t->calls[0]['headers']['Authorization']]);
    }

    public function testTheExtraQueryIsAppendedAfterTheDeclaredParametersAndNeverReplacesOne(): void
    {
        $t = new FakeTransport([[200, ['result' => true]], [200, ['result' => true]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        $sr->api->platformAppWallet(locale: 'fr', extraQuery: ['locale' => 'de', 'multiple' => 1, 'flag' => true, 'skip' => null]);
        $this->assertSame(['locale' => 'fr', 'multiple' => '1', 'flag' => '1'], $t->calls[0]['query']);
        $sr->api->customerAddressByPostcodePostcodeGet('M5V 2T6', extraQuery: ['multiple' => 1]);
        $this->assertSame('/app/v1/address-by-postcode/M5V%202T6', $t->calls[1]['path']);
        $this->assertSame(['multiple' => '1'], $t->calls[1]['query']);
    }

    public function testResultFalseIsRaisedEvenWithStatus200AndOtherAnswersAreReturned(): void
    {
        $refusal = [
            'result' => false, 'code' => 'LABEL_CANCEL_FAILED', 'message' => 'The label could not be cancelled.',
            'errors' => ['label' => ['The carrier refused the cancel.'], 'amount' => 5], 'reasons' => ['carrier_refused'],
            'data' => ['order_id' => 9],
        ];
        $t = new FakeTransport([[200, $refusal, ['X-Request-Id' => 'req-9']], [200, ['result' => true, 'data' => 1]], [200, ['items' => []]], [200, [1, 2]]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        $e = self::failure(static fn () => $sr->api->platformAppWallet());
        $this->assertSame(200, $e->status);
        $this->assertSame('LABEL_CANCEL_FAILED', $e->errorCode);
        $this->assertSame('The label could not be cancelled.', $e->getMessage());
        $this->assertSame(['label' => ['The carrier refused the cancel.'], 'amount' => ['5']], $e->errors);
        $this->assertSame(['carrier_refused'], $e->reasons);
        $this->assertSame(['order_id' => 9], $e->data);
        $this->assertSame($refusal, $e->body);
        $this->assertSame('req-9', $e->requestId);
        $this->assertSame(['result' => true, 'data' => 1], $sr->api->platformAppWallet());
        $this->assertSame(['items' => []], $sr->api->platformAppWallet());
        $this->assertSame([1, 2], $sr->api->platformAppWallet());
    }

    public function testARefusedAnswerCarriesItsHeadersRetryAfterAndRequestId(): void
    {
        $t = new FakeTransport([[429, ['error' => 'too_many_requests'], ['Retry-After' => '7', 'X-Request-Id' => 'req-1', 'X-Extra' => 'a']]]);
        $e = self::failure(fn () => self::client($t, ['tokenStore' => self::signedIn()])->api->platformAppWalletCreateTopUp(['amount' => 1]));
        self::assertSame(429, $e->status);
        self::assertSame(['retry-after' => '7', 'x-request-id' => 'req-1', 'x-extra' => 'a'], $e->headers);
        self::assertSame(7, $e->retryAfter);
        self::assertSame('req-1', $e->requestId);
    }

    public function testRetryAfterAsADateOrUnreadable(): void
    {
        $later = gmdate('D, d M Y H:i:s \G\M\T', time() + 30);
        $past = gmdate('D, d M Y H:i:s \G\M\T', time() - 30);
        $t = new FakeTransport([[503, null, ['Retry-After' => $later]], [503, null, ['Retry-After' => $past]], [503, null, ['Retry-After' => 'soon']]]);
        $sr = self::client($t, ['tokenStore' => self::signedIn()]);
        $a = self::failure(fn () => $sr->api->platformAppWalletCreateTopUp(['amount' => 1]))->retryAfter;
        self::assertNotNull($a);
        self::assertGreaterThanOrEqual(28, $a);
        self::assertLessThanOrEqual(31, $a);
        self::assertSame(0, self::failure(fn () => $sr->api->platformAppWalletCreateTopUp(['amount' => 1]))->retryAfter);
        self::assertNull(self::failure(fn () => $sr->api->platformAppWalletCreateTopUp(['amount' => 1]))->retryAfter);
    }

    public function testANetworkErrorHasNoHeadersAndNoRetryAfter(): void
    {
        $t = new FakeTransport([['network', null]]);
        $e = self::failure(fn () => self::client($t, ['tokenStore' => self::signedIn()])->api->platformAppWalletCreateTopUp(['amount' => 1]));
        self::assertSame([0, 'network_error', [], null], [$e->status, $e->errorCode, $e->headers, $e->retryAfter]);
    }

    public function testTheTokenEndpointAndCallOperationRefusalsCarryTheMetadata(): void
    {
        $t = new FakeTransport([
            [400, ['error' => 'invalid_grant'], ['X-Request-Id' => 'req-t', 'Retry-After' => '3']],
            [200, ['result' => false, 'code' => 'NOPE'], ['X-Request-Id' => 'req-o']],
            [200, ['errors' => [['message' => 'Unauthorized']]], ['X-Request-Id' => 'req-g']],
        ]);
        $sr = self::client($t, ['appKey' => 'sp_appkey_1']);
        $e = self::failure(fn () => $sr->signInWithPassword('a', 'b'));
        self::assertSame([400, 'req-t', 3, 'req-t'], [$e->status, $e->requestId, $e->retryAfter, $e->headers['x-request-id']]);
        $e = self::failure(fn () => $sr->callOperation(new Operation('GET', '/app/v1/clients/{clientCode}/guest/things', 'key', [], null)));
        self::assertSame(['NOPE', 'req-o', ['x-request-id' => 'req-o']], [$e->errorCode, $e->requestId, $e->headers]);
        $e = self::failure(fn () => $sr->graphql('{ x }'));
        self::assertSame(['graphql_error', 'req-g'], [$e->errorCode, $e->requestId]);
    }

    public function testTheOldConstructorKeepsWorking(): void
    {
        $e = new SuperrouteException(403, 'x', 'Refused.', ['a' => 1], 'req-2');
        self::assertSame([403, 'x', 'Refused.', 'req-2', [], null], [$e->status, $e->errorCode, $e->getMessage(), $e->requestId, $e->headers, $e->retryAfter]);
        $joined = SuperrouteException::from(429, null, null, ['Retry-After' => '5', 'Set-Cookie' => ['a', 'b']]);
        self::assertSame(['retry-after' => '5', 'set-cookie' => 'a, b'], $joined->headers);
        self::assertSame(5, $joined->retryAfter);
    }
}
