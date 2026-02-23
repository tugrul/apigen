# Error Handling

## ApiException

`DefaultSdkClient` throws `Tugrul\ApiGen\Exception\ApiException` for any HTTP **4xx** or **5xx** response. The exception provides full access to the original request and response.

```php
use Tugrul\ApiGen\Exception\ApiException;

try {
    $user = $api->getUser(999);
} catch (ApiException $e) {
    $e->getCode();         // int — HTTP status code, e.g. 404
    $e->getMessage();      // string — "API request failed with HTTP 404"
    $e->getRequest();      // Psr\Http\Message\RequestInterface
    $e->getResponse();     // Psr\Http\Message\ResponseInterface
    $e->getResponseBody(); // string — raw response body
}
```

`ApiException` extends `\RuntimeException`, so you can catch it alongside other runtime exceptions, or use it in a catch-all:

```php
try {
    $result = $api->createOrder($data);
} catch (ApiException $e) {
    match ($e->getCode()) {
        400 => throw new ValidationException(json_decode($e->getResponseBody(), true)),
        401 => throw new UnauthorizedException(),
        404 => throw new NotFoundException("Order endpoint not found"),
        429 => throw new RateLimitException((int) $e->getResponse()->getHeaderLine('Retry-After')),
        default => throw new ApiCallFailedException($e->getMessage(), previous: $e),
    };
}
```

---

## Exception Reference

| Scenario | Exception | Message pattern |
|---|---|---|
| HTTP 4xx or 5xx from API | `ApiException` | `API request failed with HTTP {status}` |
| `generate()` called with a class, not an interface | `\InvalidArgumentException` | `[MyClass] must be an interface.` |
| OAuth 2 token endpoint returns 4xx/5xx | `\RuntimeException` | `OAuth2 token request failed [{status}]: {body}` |
| OAuth 2 response has no `access_token` | `\RuntimeException` | `OAuth2 token response missing access_token field.` |
| `ClientBuilder::build()` called with missing PSR dependency | `\LogicException` | `ClientBuilder: [{prop}] must be provided before calling build().` |
| `withOAuth2ClientCredentials()` called before PSR-18 is set | `\LogicException` | `HTTP client and PSR-17 factories must be set before configuring OAuth2 auth.` |
| `ProxyRegistry::get()` called with unknown name | `\OutOfBoundsException` | `No stub registered under the name [{name}].` |
| `ProxyRegistry::fromAnnotated()` class has no `#[ApiNamespace]` | `\InvalidArgumentException` | `[MyStub] has no #[ApiNamespace] attribute...` |
| `AuthResolver::fromClass()` given non-existent class | `\InvalidArgumentException` | `Auth strategy class [{class}] not found.` |
| `AuthResolver::fromClass()` class does not implement `AuthStrategy` | `\InvalidArgumentException` | `[{class}] must implement AuthStrategy` |

---

## PSR-18 Transport Errors

Errors at the HTTP transport level (connection refused, timeout, DNS failure) are thrown by the PSR-18 client as `Psr\Http\Client\ClientExceptionInterface` and its sub-types. These are **not** caught or wrapped by `DefaultSdkClient` — they propagate directly to your code.

```php
use Psr\Http\Client\{ClientExceptionInterface, NetworkExceptionInterface, RequestExceptionInterface};

try {
    $result = $api->getUser(1);
} catch (ApiException $e) {
    // HTTP error response (4xx/5xx)
} catch (NetworkExceptionInterface $e) {
    // Connection-level failure (timeout, DNS, etc.)
} catch (ClientExceptionInterface $e) {
    // Any other PSR-18 transport error
}
```
