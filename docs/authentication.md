# Authentication

## Setting a Default Auth Strategy

Pass an auth strategy to `ClientBuilder`. It applies to every request that does not override it with `#[NoAuth]` or `#[UseAuth]`.

```php
$client = ClientBuilder::create('https://api.example.com')
    ->withPsr18(/* ... */)
    ->withBearerToken('my-token')   // ← default strategy
    ->build();
```

---

## Built-in Strategies

### Bearer Token

Sets `Authorization: Bearer {token}`.

```php
->withBearerToken('eyJhbGciOiJSUzI1Ni...')
```

`BearerTokenAuth` is immutable but provides `withToken(string $token): self` if you need a cloned instance with a different token.

### API Key

Sends the key in a header (default) or as a query parameter.

```php
// Header — default header name is X-Api-Key
->withApiKey('key123')
->withApiKey('key123', 'X-Custom-Key')

// Query string
use Tugrul\ApiGen\Auth\Strategy\ApiKeyAuth;

->withApiKey('key123', 'api_key', ApiKeyAuth::LOCATION_QUERY)
// → ?api_key=key123  (appended to existing query string)
```

### HTTP Basic

Sets `Authorization: Basic base64(username:password)`.

```php
->withBasicAuth('alice', 'correct-horse-battery-staple')
```

### Static Token

A literal token in any header with an optional prefix. More flexible than `withBearerToken` when the header name or prefix is non-standard.

```php
->withStaticToken('raw-token', 'X-Auth-Token')           // X-Auth-Token: raw-token
->withStaticToken('my-secret', 'Authorization', 'Token') // Authorization: Token my-secret
```

### HMAC Signature

Computes an HMAC signature over the request body (or a custom payload) and sets it as a header.

```php
->withHmacSignature('my-secret', 'sha256', 'X-Signature')
// X-Signature: hmac <hex-digest>
```

For a custom payload (e.g. sign URL + timestamp instead of body):

```php
use Tugrul\ApiGen\Auth\Strategy\HmacSignatureAuth;

new HmacSignatureAuth(
    secret:           'my-secret',
    algorithm:        'sha256',
    headerName:       'X-Sig',
    prefix:           '',
    payloadExtractor: fn($request) => $request->getUri()->getPath() . time(),
)
```

### OAuth 2.0 Client Credentials

Automatically fetches a token from the token endpoint, caches it, and refreshes it before it expires. The token is sent as `Authorization: Bearer {token}`.

```php
->withOAuth2ClientCredentials(
    tokenUrl:     'https://auth.example.com/oauth/token',
    clientId:     'my-client-id',
    clientSecret: 'my-client-secret',
    scope:        'read write',           // optional
)
```

**Auto-refresh:** Before each request, the strategy checks if the cached token is expired (within a configurable leeway). If so, it calls the token endpoint automatically.

**Force expiry (401 handling):**

```php
$auth = new OAuth2ClientCredentialsAuth(/* ... */);

// After receiving a 401, force a fresh token on the next request
$auth->forceExpire();
```

---

## OAuth 2 Token Cache

The token cache is pluggable. Pass any `TokenCache` implementation to `withOAuth2ClientCredentials()`.

| Driver | Class | Use when |
|---|---|---|
| In-memory | `InMemoryTokenCache` *(default)* | Single PHP process. Token re-fetched on every new process. |
| PHP Session | `SessionTokenCache` | Web apps. Token persists in `$_SESSION` across HTTP requests. |
| Filesystem | `FileTokenCache($dir)` | CLI tools, cron jobs. Writes one JSON file per cache key. |
| PSR-16 | `Psr16TokenCache($psr16)` | Redis, Memcached, APCu, Doctrine Cache, Symfony Cache. |

```php
use Symfony\Component\Cache\Adapter\RedisAdapter;use Symfony\Component\Cache\Psr16Cache;use Tugrul\ApiGen\Auth\{TokenCache\FileTokenCache,TokenCache\Psr16TokenCache,TokenCache\SessionTokenCache};

// Filesystem
->withOAuth2ClientCredentials($url, $id, $secret,
    cache: new FileTokenCache(__DIR__ . '/tokens')
);

// PHP session
->withOAuth2ClientCredentials($url, $id, $secret,
    cache: new SessionTokenCache()
);

// Redis via Symfony Cache

$redis = RedisAdapter::createConnection('redis://localhost');
->withOAuth2ClientCredentials($url, $id, $secret,
    cache: new Psr16TokenCache(new Psr16Cache(new RedisAdapter($redis)))
);
```

### Custom Cache Key

By default the cache key is a SHA-256 hash of `tokenUrl|clientId|scope`, ensuring uniqueness when multiple instances share the same cache. Override it when you need a stable, human-readable key:

```php
->withOAuth2ClientCredentials($url, $id, $secret, cacheKey: 'my-service-oauth-token');
```

### Leeway

Tokens are considered expired `leewaySeconds` before their actual expiry time. The default is 30 seconds. This prevents using a token that would expire mid-request.

```php
new OAuth2ClientCredentialsAuth(
    /* ... */
    leewaySeconds: 60,   // treat token as expired 60s before actual expiry
);
```

### Implementing a Custom Cache Driver

```php
use Tugrul\ApiGen\Auth\TokenCache;

final class DoctrineCacheDriver implements TokenCache
{
    public function __construct(private \Doctrine\Common\Cache\Cache $cache) {}

    public function get(string $key): ?array
    {
        $data = $this->cache->fetch('apigen:' . $key);
        return $data !== false ? $data : null;
    }

    public function set(string $key, array $entry): void
    {
        $ttl = max(1, $entry['expires_at'] - time() - 30);
        $this->cache->save('apigen:' . $key, $entry, $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->delete('apigen:' . $key);
    }
}
```

---

## Custom Auth Strategy

Implement `AuthStrategy` to support any authentication scheme:

```php
use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

final class AwsSigV4Auth implements AuthStrategy
{
    public function __construct(
        private readonly string $accessKey,
        private readonly string $secretKey,
        private readonly string $region,
        private readonly string $service,
    ) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $authHeader = $this->buildSigV4Header($request);

        return $request
            ->withHeader('Authorization', $authHeader)
            ->withHeader('X-Amz-Date', gmdate('Ymd\THis\Z'));
    }

    private function buildSigV4Header(RequestInterface $request): string
    {
        // ... AWS SigV4 implementation
    }
}

// Use it
ClientBuilder::create('https://s3.amazonaws.com')
    ->withPsr18(/* ... */)
    ->withAuth(new AwsSigV4Auth($key, $secret, 'us-east-1', 's3'))
    ->build();
```

### Marker Interfaces

Implement these optional marker interfaces for richer capabilities:

**`SigningAuth`** — exposes a `sign()` method. Useful when the signature needs to be inspected independently (e.g. for debugging or webhook verification):

```php
use Tugrul\ApiGen\Contracts\SigningAuth;

final class HmacAuth implements AuthStrategy, SigningAuth
{
    public function sign(RequestInterface $request): string
    {
        return hash_hmac('sha256', (string) $request->getBody(), $this->secret);
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('X-Signature', $this->sign($request));
    }
}
```

**`RefreshableAuth`** — lifecycle hooks for token-based strategies:

```php
use Tugrul\ApiGen\Contracts\RefreshableAuth;

final class MyTokenAuth implements AuthStrategy, RefreshableAuth
{
    public function isExpired(): bool { /* ... */ }
    public function refresh(): void  { /* fetch new token */ }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        if ($this->isExpired()) {
            $this->refresh();
        }
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }
}
```

---

## Per-endpoint Auth Override

### `#[NoAuth]` — Disable Auth

```php
interface PublicApi
{
    #[GET('/status')]
    #[NoAuth]                   // no Authorization header sent
    public function healthCheck(): array;
}
```

### `#[UseAuth]` — Different Strategy per Endpoint

```php
interface MixedApi
{
    #[GET('/data')]
    public function getData(): array;   // uses client default auth

    #[POST('/admin/reset')]
    #[UseAuth(AdminTokenAuth::class)]   // uses AdminTokenAuth for this endpoint only
    public function adminReset(): void;
}

// Register the named strategy:
ClientBuilder::create('https://api.example.com')
    ->withPsr18(/* ... */)
    ->withBearerToken('default-token')
    ->withNamedStrategy(AdminTokenAuth::class, new AdminTokenAuth($adminSecret))
    ->build();
```

---

## Auth Priority

For each request, the effective auth strategy is resolved in this order:

1. `#[NoAuth]` on the method → **no auth**
2. `#[UseAuth(SomeClass::class)]` on the method → **named strategy**
3. Explicit `auth()` call on `CallDescriptor` (used in generated code) → **call-level strategy**
4. Client default (set via `withBearerToken`, `withAuth`, etc.) → **default strategy**
5. Nothing configured → **no auth**
