# Tugrul ApiGen

**Generate REST API SDK clients from annotated PHP interfaces.**

Define your API surface as a PHP interface, decorate methods with attributes, run the generator — done. ApiGen handles URL building, parameter binding, body encoding, authentication, and response decoding. You bring the PSR-18 HTTP client of your choice.

```php
// 1. Define
interface UserApi {
    #[GET('/users/{id}')]
    #[Returns('array')]
    public function getUser(#[Path] int $id): array;

    #[DELETE('/users/{id}')]
    public function deleteUser(#[Path] int $id): void;
}

// 2. Generate (once, at development time)
$gen = new StubGenerator(__DIR__ . '/Generated');
$gen->generate(UserApi::class);

// 3. Use
$client = ClientBuilder::create('https://api.example.com')
    ->withPsr18($httpClient, $psr17, $psr17, $psr17)
    ->withBearerToken('my-token')
    ->build();

$api  = new UserApiImpl($client);
$user = $api->getUser(42);
$api->deleteUser(42);
```

---

## Requirements

| Requirement | Version |
|---|---|
| PHP | ≥ 8.1 |
| psr/http-client | ^1.0 |
| psr/http-factory | ^1.0 |
| psr/http-message | ^1.1 \|\| ^2.0 |

## Installation

```bash
composer require tugrul/apigen
```

## Documentation

| Topic | Description |
|---|---|
| [Quick Start](docs/quick-start.md) | Define → Generate → Use in 5 minutes |
| [Attributes Reference](docs/attributes.md) | Every attribute, target, and parameter |
| [Authentication](docs/authentication.md) | Built-in strategies, OAuth 2, custom auth |
| [Code Generation](docs/generation.md) | StubGenerator, naming strategies, PSR-4 paths |
| [CLI Tool](docs/cli.md) | `apigen generate`, `apigen list`, config files |
| [Client Configuration](docs/client.md) | ClientBuilder, middleware, custom decoders |
| [ProxyRegistry](docs/proxy.md) | Group multiple stubs under one object |
| [Error Handling](docs/errors.md) | ApiException, error table |
| [Testing](docs/testing.md) | Running tests, testing your own stubs |
| [Architecture](docs/architecture.md) | Package structure, request lifecycle |

## Key Features

- **Attribute-driven** — HTTP verbs, path/query/header/body binding, auth, encoding, and response types are all declared with native PHP 8 attributes. No XML, no YAML, no code to write beyond the interface.
- **PSR-18 transport** — bring your own HTTP client. Guzzle, Symfony HttpClient, Buzz, or any PSR-18 implementation.
- **Pluggable auth** — Bearer token, API key, Basic, HMAC, OAuth 2 client credentials, or your own `AuthStrategy`. Per-endpoint overrides with `#[UseAuth]`.
- **OAuth 2 token caching** — in-memory, PHP session, filesystem, or any PSR-16 store (Redis, Memcached, APCu). Auto-refresh with configurable leeway.
- **Five naming strategies** — control the generated class name and output namespace. Default, Suffix, Sub-namespace, Callable, and per-interface Map.
- **PSR-4 aware output paths** — reads `composer.json` to compute the correct subdirectory offset, avoiding double-nesting of namespace roots.
- **CLI tool** — `apigen generate` and `apigen list` commands with config file support.
- **Middleware** — chain pre/post request hooks for logging, retry, rate-limiting, and more.

## At a Glance

```
┌─────────────────────────────────────────────────────────────┐
│  Your PHP Interface  +  Attributes                          │
│        ↓  (apigen generate)                                 │
│  Generated Stub Class  (concrete, final, PSR-18 backed)     │
│        ↓                                                    │
│  ClientBuilder → DefaultSdkClient → PSR-18 HTTP Client      │
└─────────────────────────────────────────────────────────────┘
```

## License

MIT
