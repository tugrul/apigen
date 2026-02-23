# Client Configuration

## ClientBuilder

`ClientBuilder` is the fluent entry point for constructing a `DefaultSdkClient`. All generated stubs accept a `SdkClient` in their constructor — `ClientBuilder::build()` returns one.

```php
use Tugrul\ApiGen\Http\ClientBuilder;

$client = ClientBuilder::create('https://api.example.com')
    ->withPsr18($httpClient, $requestFactory, $streamFactory, $uriFactory)
    ->withBearerToken('my-token')
    ->build();
```

### Method Reference

| Method | Description |
|---|---|
| `::create(string $baseUrl)` | Static factory. Base URL trailing slash is stripped automatically. |
| `->withPsr18($client, $reqFact, $streamFact, $uriFact)` | Set all PSR-18/17 dependencies at once. |
| `->withHttpClient(ClientInterface $client)` | Set just the PSR-18 HTTP client. |
| `->withRequestFactory(RequestFactoryInterface $f)` | Set just the PSR-17 request factory. |
| `->withStreamFactory(StreamFactoryInterface $f)` | Set just the PSR-17 stream factory. |
| `->withUriFactory(UriFactoryInterface $f)` | Set just the PSR-17 URI factory. |
| `->withBearerToken(string $token)` | Use `BearerTokenAuth` as the default strategy. |
| `->withApiKey($key, $header, $location)` | Use `ApiKeyAuth`. Location: `LOCATION_HEADER` *(default)* or `LOCATION_QUERY`. |
| `->withBasicAuth($user, $pass)` | Use `BasicAuth`. |
| `->withStaticToken($token, $header, $prefix)` | Use `StaticTokenAuth`. |
| `->withHmacSignature($secret, $algo, $header)` | Use `HmacSignatureAuth`. |
| `->withOAuth2ClientCredentials(...)` | Use `OAuth2ClientCredentialsAuth`. See [Authentication](authentication.md). |
| `->withAuth(AuthStrategy $auth)` | Set any custom `AuthStrategy` as the default. |
| `->withNamedStrategy($key, AuthStrategy $strategy)` | Register a named strategy for `#[UseAuth]` resolution. |
| `->withDecoder(ResponseDecoder $decoder)` | Set a custom response decoder. |
| `->build()` | Construct and return the `DefaultSdkClient`. Throws `\LogicException` if required PSR dependencies are missing. |

### Example with Guzzle

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Tugrul\ApiGen\Http\ClientBuilder;

$factory = new HttpFactory();   // implements PSR-17 RequestFactory + StreamFactory + UriFactory

$client = ClientBuilder::create('https://api.example.com')
    ->withPsr18(
        client:         new Client(),
        requestFactory: $factory,
        streamFactory:  $factory,
        uriFactory:     $factory,
    )
    ->withBearerToken('my-token')
    ->build();
```

### Example with Symfony HttpClient

```php
use Symfony\Component\HttpClient\Psr18Client;
use Tugrul\ApiGen\Http\ClientBuilder;

$psr18 = new Psr18Client();  // implements PSR-18 + PSR-17

$client = ClientBuilder::create('https://api.example.com')
    ->withPsr18($psr18, $psr18, $psr18, $psr18)
    ->withApiKey('my-api-key')
    ->build();
```

---

## Request Middleware

Implement `RequestMiddleware` to add hooks that run **before the request is sent**. Chain as many as needed by calling `addRequestMiddleware()` multiple times.

```php
use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\RequestMiddleware;

// Logging middleware
$client->addRequestMiddleware(new class implements RequestMiddleware {
    public function before(RequestInterface $request): RequestInterface
    {
        error_log(sprintf('[API] %s %s', $request->getMethod(), $request->getUri()));
        return $request;
    }
});

// Correlation ID middleware
$client->addRequestMiddleware(new class implements RequestMiddleware {
    public function before(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('X-Correlation-Id', uniqid('req-', true));
    }
});
```

Middleware runs in registration order. Each middleware receives the request returned by the previous one.

---

## Response Middleware

Implement `ResponseMiddleware` to add hooks that run **after the response is received** (but before error checking or decoding).

```php
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Tugrul\ApiGen\Contracts\ResponseMiddleware;

// 401 auto-refresh middleware
$client->addResponseMiddleware(new class($auth) implements ResponseMiddleware {
    public function __construct(private readonly OAuth2ClientCredentialsAuth $auth) {}

    public function after(RequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        if ($res->getStatusCode() === 401) {
            $this->auth->forceExpire();
        }
        return $res;
    }
});

// Rate-limit header logging
$client->addResponseMiddleware(new class implements ResponseMiddleware {
    public function after(RequestInterface $req, ResponseInterface $res): ResponseInterface
    {
        $remaining = $res->getHeaderLine('X-RateLimit-Remaining');
        if ($remaining !== '' && (int) $remaining < 10) {
            error_log("[API] Rate limit low: {$remaining} remaining");
        }
        return $res;
    }
});
```

---

## Custom Response Decoder

By default, `DefaultSdkClient` JSON-decodes the response body. If a `#[Returns(SomeClass::class)]` hint is present and `SomeClass` exists, it attempts to instantiate it. Replace this with a `ResponseDecoder` for more sophisticated mapping, XML, MessagePack, etc.

```php
use Tugrul\ApiGen\Contracts\ResponseDecoder;

final class SymfonySerializerDecoder implements ResponseDecoder
{
    public function __construct(
        private readonly \Symfony\Component\Serializer\SerializerInterface $serializer
    ) {}

    public function decode(string $body, ?string $type, ?string $genericOf): mixed
    {
        if ($type === null || $type === 'array' || $type === 'mixed') {
            return json_decode($body, true);
        }

        return $this->serializer->deserialize($body, $type, 'json');
    }
}

$client = ClientBuilder::create('https://api.example.com')
    ->withPsr18(/* ... */)
    ->withDecoder(new SymfonySerializerDecoder($serializer))
    ->build();
```

The decoder receives:
- `$body` — raw response body string
- `$type` — value from `#[Returns(Type::class)]`, or `null`
- `$genericOf` — second argument from `#[Returns('array', ItemType::class)]`, or `null`

---

## Default Decoding Behaviour

When no custom `ResponseDecoder` is set, `DefaultSdkClient` decodes responses as follows:

| Condition | Result |
|---|---|
| Empty body | `null` |
| `$type` is `null`, `'array'`, or `'mixed'` | `json_decode($body, true)` |
| `$type` is a class name and `$body` decodes to array | `new $type(...$data)` |
| Otherwise | `json_decode($body, true)` |
