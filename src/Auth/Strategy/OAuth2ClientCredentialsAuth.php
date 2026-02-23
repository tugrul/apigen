<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestFactoryInterface, RequestInterface, StreamFactoryInterface};
use Tugrul\ApiGen\Auth\TokenCache;
use Tugrul\ApiGen\Auth\TokenCache\InMemoryTokenCache;
use Tugrul\ApiGen\Contracts\{AuthStrategy, RefreshableAuth};

// ---------------------------------------------------------------------------
// OAuth 2 Client Credentials (fetches + caches token, pluggable cache drivers)
// ---------------------------------------------------------------------------

final class OAuth2ClientCredentialsAuth implements AuthStrategy, RefreshableAuth
{
    /**
     * Runtime fallback — only used when the injected cache returns nothing.
     * Survives only the current PHP process / request lifecycle.
     */
    private ?string $runtimeToken     = null;
    private ?int    $runtimeExpiresAt = null;

    private readonly TokenCache $cache;
    private readonly string     $cacheKey;

    /**
     * @param TokenCache|null $cache
     *   Inject any TokenCache implementation:
     *     - null / omit          → InMemoryTokenCache (process-scoped, default)
     *     - SessionTokenCache    → PHP native session
     *     - FileTokenCache       → filesystem JSON files
     *     - Psr16TokenCache      → Redis, Memcached, APCu, Doctrine Cache, …
     *     - Your own class       → implement TokenCache
     *
     * @param string|null $cacheKey
     *   Override to avoid collisions when multiple instances share the same cache store.
     *   Defaults to a SHA-256 hash of (tokenUrl + clientId + scope).
     */
    public function __construct(
        private readonly ClientInterface         $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface  $streamFactory,
        private readonly string   $tokenUrl,
        private readonly string   $clientId,
        private readonly string   $clientSecret,
        private readonly string   $scope         = '',
        private readonly int      $leewaySeconds = 30,
        ?TokenCache               $cache         = null,
        ?string                   $cacheKey      = null,
    ) {
        $this->cache    = $cache ?? new InMemoryTokenCache();
        $this->cacheKey = $cacheKey ?? hash('sha256', implode('|', [
            $tokenUrl, $clientId, $scope,
        ]));
    }

    // --- RefreshableAuth ---

    public function isExpired(): bool
    {
        $entry = $this->cache->get($this->cacheKey);

        if ($entry !== null) {
            return time() >= ($entry['expires_at'] - $this->leewaySeconds);
        }

        return $this->runtimeToken === null
            || $this->runtimeExpiresAt === null
            || time() >= ($this->runtimeExpiresAt - $this->leewaySeconds);
    }

    public function refresh(): void
    {
        $formBody = http_build_query(array_filter([
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => $this->scope,
        ]));

        $request = $this->requestFactory
            ->createRequest('POST', $this->tokenUrl)
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream($formBody));

        $response = $this->httpClient->sendRequest($request);

        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(
                "OAuth2 token request failed [{$response->getStatusCode()}]: " .
                (string) $response->getBody()
            );
        }

        $data = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        if (empty($data['access_token'])) {
            throw new \RuntimeException('OAuth2 token response missing access_token field.');
        }

        $expiresAt = isset($data['expires_in'])
            ? time() + (int) $data['expires_in']
            : time() + 3600;

        $entry = ['access_token' => $data['access_token'], 'expires_at' => $expiresAt];

        $this->cache->set($this->cacheKey, $entry);
        $this->runtimeToken     = $data['access_token'];
        $this->runtimeExpiresAt = $expiresAt;
    }

    /**
     * Force-expire the cached token (e.g. call after receiving a 401 response).
     * The next authenticate() call will fetch a fresh token automatically.
     */
    public function forceExpire(): void
    {
        $this->cache->delete($this->cacheKey);
        $this->runtimeToken     = null;
        $this->runtimeExpiresAt = null;
    }

    // --- AuthStrategy ---

    public function authenticate(RequestInterface $request): RequestInterface
    {
        if ($this->isExpired()) {
            $this->refresh();
        }

        return $request->withHeader('Authorization', "Bearer {$this->resolveToken()}");
    }

    // --- Internals ---

    private function resolveToken(): string
    {
        $entry = $this->cache->get($this->cacheKey);

        if ($entry !== null) {
            return $entry['access_token'];
        }

        if ($this->runtimeToken !== null) {
            return $this->runtimeToken;
        }

        throw new \LogicException('No access token available after refresh.');
    }
}
