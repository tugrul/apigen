<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Auth;

use Tugrul\ApiGen\Auth\Strategy\OAuth2ClientCredentialsAuth;
use Tugrul\ApiGen\Auth\TokenCache\InMemoryTokenCache;
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class OAuth2ClientCredentialsAuthTest extends ApiGenTestCase
{
    private function makeAuth(
        array $tokenResponses,
        ?InMemoryTokenCache $cache = null,
        int $leeway = 30,
        ?string $cacheKey = null,
    ): array {
        $responses  = array_map(fn($r) => $this->makeJsonResponse($r), $tokenResponses);
        $httpClient = $this->mockHttpClient(...$responses);

        $auth = new OAuth2ClientCredentialsAuth(
            httpClient:     $httpClient,
            requestFactory: $this->psr17,
            streamFactory:  $this->psr17,
            tokenUrl:       'https://auth.example.com/token',
            clientId:       'client-id',
            clientSecret:   'client-secret',
            scope:          'read',
            leewaySeconds:  $leeway,
            cache:          $cache,
            cacheKey:       $cacheKey,
        );

        return [$auth, $httpClient];
    }

    // ── Initial state ─────────────────────────────────────────────────────────

    public function test_is_expired_initially(): void
    {
        [$auth] = $this->makeAuth([]);

        self::assertTrue($auth->isExpired());
    }

    // ── Successful token fetch ────────────────────────────────────────────────

    public function test_authenticate_fetches_token_and_sets_bearer_header(): void
    {
        [$auth, $httpClient] = $this->makeAuth([
            ['access_token' => 'tok-abc', 'expires_in' => 3600],
        ]);

        $result = $auth->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'Authorization', 'Bearer tok-abc');
        self::assertCount(1, $httpClient->capturedRequests);
    }

    public function test_token_request_sends_correct_form_fields(): void
    {
        [$auth, $httpClient] = $this->makeAuth([
            ['access_token' => 'tok', 'expires_in' => 3600],
        ]);

        $auth->authenticate($this->makeRequest());

        $tokenRequest = $httpClient->capturedRequests[0];
        parse_str((string) $tokenRequest->getBody(), $fields);

        self::assertSame('client_credentials', $fields['grant_type']);
        self::assertSame('client-id',          $fields['client_id']);
        self::assertSame('client-secret',       $fields['client_secret']);
        self::assertSame('read',               $fields['scope']);
        self::assertSame('application/x-www-form-urlencoded', $tokenRequest->getHeaderLine('Content-Type'));
    }

    // ── Caching ───────────────────────────────────────────────────────────────

    public function test_token_not_refetched_when_not_expired(): void
    {
        [$auth, $httpClient] = $this->makeAuth([
            ['access_token' => 'tok', 'expires_in' => 3600],
        ]);

        $auth->authenticate($this->makeRequest());
        $auth->authenticate($this->makeRequest());

        // Only one HTTP call even though we authenticated twice
        self::assertCount(1, $httpClient->capturedRequests);
    }

    public function test_token_stored_in_provided_cache(): void
    {
        $cache = new InMemoryTokenCache();

        [$auth] = $this->makeAuth(
            [['access_token' => 'cached-tok', 'expires_in' => 3600]],
            cache: $cache,
            cacheKey: 'my-key',
        );

        $auth->authenticate($this->makeRequest());

        $entry = $cache->get('my-key');
        self::assertNotNull($entry);
        self::assertSame('cached-tok', $entry['access_token']);
    }

    public function test_token_read_from_pre_populated_cache_without_http_call(): void
    {
        $cache = new InMemoryTokenCache();
        $cache->set('pre-key', ['access_token' => 'pre-tok', 'expires_at' => time() + 3600]);

        [$auth, $httpClient] = $this->makeAuth([], cache: $cache, cacheKey: 'pre-key');

        $result = $auth->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'Authorization', 'Bearer pre-tok');
        self::assertCount(0, $httpClient->capturedRequests); // no HTTP call made
    }

    // ── Leeway / expiry ───────────────────────────────────────────────────────

    public function test_token_treated_as_expired_within_leeway_window(): void
    {
        // Token expires in 20s, leeway is 30s → should be considered expired
        $cache = new InMemoryTokenCache();
        $cache->set('k', ['access_token' => 'old', 'expires_at' => time() + 20]);

        [$auth, $httpClient] = $this->makeAuth(
            [['access_token' => 'new-tok', 'expires_in' => 3600]],
            cache: $cache,
            leeway: 30,
            cacheKey: 'k',
        );

        $result = $auth->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'Authorization', 'Bearer new-tok');
        self::assertCount(1, $httpClient->capturedRequests);
    }

    public function test_token_valid_outside_leeway_window(): void
    {
        // Token expires in 60s, leeway is 30s → still valid
        $cache = new InMemoryTokenCache();
        $cache->set('k', ['access_token' => 'still-valid', 'expires_at' => time() + 60]);

        [$auth, $httpClient] = $this->makeAuth([], cache: $cache, leeway: 30, cacheKey: 'k');

        $result = $auth->authenticate($this->makeRequest());

        $this->assertRequestHeader($result, 'Authorization', 'Bearer still-valid');
        self::assertCount(0, $httpClient->capturedRequests);
    }

    // ── forceExpire ───────────────────────────────────────────────────────────

    public function test_force_expire_causes_next_authenticate_to_refetch(): void
    {
        [$auth, $httpClient] = $this->makeAuth([
            ['access_token' => 'first',  'expires_in' => 3600],
            ['access_token' => 'second', 'expires_in' => 3600],
        ]);

        $auth->authenticate($this->makeRequest()); // fetch first token
        $auth->forceExpire();
        $result = $auth->authenticate($this->makeRequest()); // should fetch again

        $this->assertRequestHeader($result, 'Authorization', 'Bearer second');
        self::assertCount(2, $httpClient->capturedRequests);
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function test_throws_on_non_2xx_token_response(): void
    {
        $errorResponse = new \Nyholm\Psr7\Response(401, [], '{"error":"invalid_client"}');
        $httpClient    = $this->mockHttpClient($errorResponse);

        $auth = new OAuth2ClientCredentialsAuth(
            httpClient:     $httpClient,
            requestFactory: $this->psr17,
            streamFactory:  $this->psr17,
            tokenUrl:       'https://auth.example.com/token',
            clientId:       'bad',
            clientSecret:   'creds',
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/401/');

        $auth->authenticate($this->makeRequest());
    }

    public function test_throws_when_access_token_missing_from_response(): void
    {
        [$auth] = $this->makeAuth([['token_type' => 'bearer']]); // no access_token key

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/access_token/');

        $auth->authenticate($this->makeRequest());
    }

    // ── Cache key auto-generation ─────────────────────────────────────────────

    public function test_auto_generated_cache_keys_differ_for_different_scopes(): void
    {
        $cache = new InMemoryTokenCache();

        $make = fn(string $scope) => new OAuth2ClientCredentialsAuth(
            httpClient:     $this->mockHttpClient($this->makeJsonResponse(['access_token' => $scope . '-tok', 'expires_in' => 3600])),
            requestFactory: $this->psr17,
            streamFactory:  $this->psr17,
            tokenUrl:       'https://auth.example.com/token',
            clientId:       'id',
            clientSecret:   'secret',
            scope:          $scope,
            cache:          $cache,
        );

        $make('read')->authenticate($this->makeRequest());
        $make('write')->authenticate($this->makeRequest());

        $keys = array_filter(array_map(fn($k) => $cache->get($k), ['read', 'write']));
        // They should be stored under different auto-generated keys, so the cache
        // has 2 entries when we enumerate it (InMemoryTokenCache stores by SHA key)
        // We verify they are independently fetchable by reconstructing the keys
        $readKey  = hash('sha256', 'https://auth.example.com/token|id|read');
        $writeKey = hash('sha256', 'https://auth.example.com/token|id|write');

        self::assertSame('read-tok',  $cache->get($readKey)['access_token']);
        self::assertSame('write-tok', $cache->get($writeKey)['access_token']);
    }
}
