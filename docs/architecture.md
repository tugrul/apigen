# Architecture

## Package Structure

```
src/
├── Attributes/
│   ├── Method/           GET, POST, PUT, PATCH, DELETE, HEAD
│   ├── Params/           Path, Query, QueryMap, Header, Body, Field, Part
│   └── Modifiers/        Headers, StaticHeader, StaticQuery, FormUrlEncoded,
│                         Multipart, Returns, NoAuth, UseAuth, BaseUrl, ApiNamespace
│
├── Auth/
│   ├── Strategy/         NoAuth, StaticTokenAuth, BearerTokenAuth, ApiKeyAuth,
│   │                     BasicAuth, HmacSignatureAuth, OAuth2ClientCredentialsAuth
│   ├── AuthResolver.php  Selects the effective AuthStrategy per call
│   └── TokenCache.php    TokenCache interface + InMemoryTokenCache,
│                         SessionTokenCache, FileTokenCache, Psr16TokenCache
│
├── Console/
│   ├── Application.php      CLI command router (generate | list | help | version)
│   ├── GenerateCommand.php  apigen generate implementation
│   ├── ListCommand.php      apigen list implementation
│   └── Output.php           ANSI terminal output helper
│
├── Contracts/
│   ├── AuthStrategy.php  AuthStrategy, RefreshableAuth, SigningAuth
│   └── SdkClient.php     SdkClient, EndpointCall, ResponseDecoder,
│                         RequestMiddleware, ResponseMiddleware
│
├── Exception/
│   └── ApiException.php  HTTP error exception (status code, request, response)
│
├── Generator/
│   ├── GeneratedStub.php      Readonly value object returned by StubGenerator
│   ├── OutputPathResolver.php PSR-4 aware file path computation
│   ├── StubGenerator.php      Core generator — reflection + code emission
│   └── StubNamingStrategy.php Interface + 5 concrete implementations
│
├── Http/
│   ├── CallDescriptor.php     Fluent EndpointCall builder used in generated stubs
│   ├── ClientBuilder.php      Fluent DefaultSdkClient factory
│   └── DefaultSdkClient.php   PSR-18-backed SdkClient implementation
│
└── Proxy/
    └── ProxyRegistry.php  Optional named grouping of multiple stubs
```

---

## Request Lifecycle

Every API call through a generated stub follows this path:

```
Your code
    │
    ▼
Generated stub method called (e.g. $api->getUser(42))
    │  Builds a CallDescriptor from attributes:
    │    →  method('GET')
    │    →  path('/users/{id}')
    │    →  pathParam('id', 42)
    │    →  returnType('array')
    │
    ▼
DefaultSdkClient::execute(CallDescriptor)
    │
    ├─ 1. Build PSR-7 request
    │       Expand path params  /users/{id} → /users/42
    │       Append query string
    │       Set static + dynamic headers
    │       Encode body (json / form / multipart / raw)
    │
    ├─ 2. Resolve auth strategy
    │       Priority: #[NoAuth] → #[UseAuth] → call auth → client default → none
    │
    ├─ 3. Apply auth
    │       AuthStrategy::authenticate(request) → modified request
    │
    ├─ 4. Request middleware
    │       RequestMiddleware::before(request) → request  (chain, in order)
    │
    ├─ 5. HTTP send
    │       PSR-18 ClientInterface::sendRequest(request) → response
    │
    ├─ 6. Response middleware
    │       ResponseMiddleware::after(request, response) → response  (chain, in order)
    │
    ├─ 7. Error check
    │       status >= 400  →  throw ApiException
    │
    └─ 8. Decode
            Empty body → null
            Custom ResponseDecoder::decode(body, type, genericOf) if set
            Otherwise: json_decode + optional class instantiation
```

---

## Key Design Decisions

### Zero Runtime Code Generation

Stubs are generated once during development and committed to source control like any other class. The generator (`StubGenerator`) is a development-time tool — it is never invoked at request time. Generated stubs are plain PHP classes with no runtime dependencies on reflection, eval, or the generator package itself.

### PSR-18 Only

`DefaultSdkClient` depends only on `psr/http-client`, `psr/http-factory`, and `psr/http-message`. There is no dependency on any specific HTTP client library. Swap Guzzle for Symfony HttpClient, or use a mock client in tests, by swapping the PSR-18 implementation passed to `ClientBuilder`.

### Attribute-Only Interface Description

The PHP interface itself has no base class, no required trait, and no ApiGen-specific method signatures. It is a plain PHP interface. The generator reads it via reflection — your interfaces are 100% dependency-free from ApiGen's perspective. If you stop using ApiGen, your interfaces remain valid.

### Immutability

- `GeneratedStub` is `readonly` — a value object describing what was generated.
- `AuthResolver` returns a new instance from `withStrategy()` — the original is unmodified.
- PSR-7 requests and responses are immutable by the PSR-7 spec. Auth strategies and middleware always return new instances.

### Separation of Concerns

| Component | Knows About |
|---|---|
| `StubNamingStrategy` | Class names and namespaces only |
| `OutputPathResolver` | File paths only |
| `StubGenerator` | Coordinates naming + path; emits code |
| `CallDescriptor` | Request parameters only |
| `DefaultSdkClient` | HTTP execution only |
| `ClientBuilder` | Assembling `DefaultSdkClient` only |
| `AuthResolver` | Which strategy to use for a given call |

No component reaches into another's domain. This makes each part independently testable and replaceable.

---

## Contracts

Everything consumed by generated stubs is behind an interface in `Tugrul\ApiGen\Contracts`:

```php
// The only thing a generated stub depends on at runtime
interface SdkClient
{
    public function execute(EndpointCall $call): mixed;
    // + PSR-17/18 accessor methods
}

// What generated stubs build and pass to execute()
interface EndpointCall
{
    public function getMethod(): string;
    public function getPath(): string;
    public function getPathParams(): array;
    public function getQueryParams(): array;
    public function getHeaders(): array;
    public function getBody(): mixed;
    public function getBodyEncoding(): string;
    public function getAuth(): ?AuthStrategy;
    public function isAuthDisabled(): bool;
    public function getReturnType(): ?string;
    public function getReturnGenericOf(): ?string;
}
```

This means you can substitute `DefaultSdkClient` with any implementation of `SdkClient` — for testing, for adding a circuit breaker, or for a completely different transport mechanism.
