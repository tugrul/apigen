# Testing

## Running the Test Suite

```bash
composer install
composer test              # all 147 unit tests
composer test:unit         # unit suite only
composer test:coverage     # with code coverage report

# Run a specific test file
./vendor/bin/phpunit tests/Auth/OAuth2ClientCredentialsAuthTest.php

# Run tests matching a pattern
./vendor/bin/phpunit --filter test_bearer_token
```

## Test Structure

| Suite | File | Tests | Covers |
|---|---|---|---|
| Auth | `AuthStrategiesTest` | 14 | All built-in auth strategies |
| Auth | `TokenCacheTest` | 12 | `InMemoryTokenCache`, `FileTokenCache` |
| Auth | `OAuth2ClientCredentialsAuthTest` | 12 | Token fetch, caching, leeway, `forceExpire`, error cases |
| Auth | `AuthResolverTest` | 10 | Priority resolution, `fromClass`, immutability |
| Http | `CallDescriptorTest` | 18 | All fluent builder methods and defaults |
| Http | `DefaultSdkClientTest` | 18 | URL building, body encoding, auth, middleware, error handling |
| Generator | `StubGeneratorTest` | 25 | Generated code content, all attribute types, void methods |
| Generator | `StubNamingStrategyTest` | 18 | All 5 naming strategies |
| Generator | `OutputPathResolverTest` | 11 | Composer map strategy, reflection heuristic, fallback modes |
| Proxy | `ProxyRegistryTest` | 9 | `fromStubs`, `fromAnnotated`, magic property access |

---

## Testing Your Own Generated Stubs

Because generated stubs depend only on the `SdkClient` interface, you can mock the client in your tests without spinning up an actual HTTP server.

### Asserting the Correct Request is Built

```php
use PHPUnit\Framework\TestCase;
use Tugrul\ApiGen\Contracts\{SdkClient, EndpointCall};

final class UserApiStubTest extends TestCase
{
    public function test_get_user_sends_correct_request(): void
    {
        $capturedCall = null;

        $client = $this->createMock(SdkClient::class);
        $client
            ->method('execute')
            ->willReturnCallback(function (EndpointCall $call) use (&$capturedCall) {
                $capturedCall = $call;
                return ['id' => 42, 'name' => 'Alice'];
            });

        $stub   = new UserApiStub($client);
        $result = $stub->getUser(42);

        // Assert the stub built the correct CallDescriptor
        $this->assertSame('GET',         $capturedCall->getMethod());
        $this->assertSame('/users/{id}', $capturedCall->getPath());
        $this->assertSame(['id' => '42'], $capturedCall->getPathParams());

        // Assert the return value is passed through
        $this->assertSame(['id' => 42, 'name' => 'Alice'], $result);
    }

    public function test_create_user_sends_json_body(): void
    {
        $capturedCall = null;
        $client = $this->createMock(SdkClient::class);
        $client->method('execute')->willReturnCallback(function ($call) use (&$capturedCall) {
            $capturedCall = $call;
            return ['id' => 1, 'name' => 'Bob'];
        });

        (new UserApiStub($client))->createUser(['name' => 'Bob', 'email' => 'bob@example.com']);

        $this->assertSame('POST',  $capturedCall->getMethod());
        $this->assertSame('/users', $capturedCall->getPath());
        $this->assertSame(['name' => 'Bob', 'email' => 'bob@example.com'], $capturedCall->getBody());
        $this->assertSame('json',  $capturedCall->getBodyEncoding());
    }

    public function test_delete_user_is_void_and_sends_no_body(): void
    {
        $client = $this->createMock(SdkClient::class);
        $client->method('execute')->willReturn(null);

        // void methods return nothing — just assert no exception is thrown
        (new UserApiStub($client))->deleteUser(42);

        $this->addToAssertionCount(1);  // mark the test as having run
    }
}
```

### Simulating Error Responses

```php
use Tugrul\ApiGen\Exception\ApiException;

public function test_get_user_propagates_not_found(): void
{
    $request  = $this->createMock(\Psr\Http\Message\RequestInterface::class);
    $response = $this->createMock(\Psr\Http\Message\ResponseInterface::class);
    $response->method('getStatusCode')->willReturn(404);
    $response->method('getBody')->willReturn(
        \Nyholm\Psr7\Stream::create('{"error":"not found"}')
    );

    $client = $this->createMock(SdkClient::class);
    $client->method('execute')->willThrowException(
        new ApiException('API request failed with HTTP 404', 404, $request, $response)
    );

    $this->expectException(ApiException::class);
    $this->expectExceptionCode(404);

    (new UserApiStub($client))->getUser(999);
}
```

### Testing Auth Strategies

Auth strategies are pure functions — they receive a PSR-7 request and return a modified one. Test them directly:

```php
use Nyholm\Psr7\Factory\Psr17Factory;use Tugrul\ApiGen\Auth\Strategy\BearerTokenAuth;

public function test_bearer_token_sets_authorization_header(): void
{
    $factory = new Psr17Factory();
    $request = $factory->createRequest('GET', 'https://api.example.com/users');
    $auth    = new BearerTokenAuth('my-token');

    $result = $auth->authenticate($request);

    $this->assertSame('Bearer my-token', $result->getHeaderLine('Authorization'));
}
```

### Integration Testing with a Real HTTP Client

For integration tests that actually hit a test server or a WireMock/HttpMock instance:

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Tugrul\ApiGen\Http\ClientBuilder;

$client = ClientBuilder::create('http://localhost:8080')
    ->withPsr18(new Client(), new HttpFactory(), new HttpFactory(), new HttpFactory())
    ->withBearerToken('test-token')
    ->build();

$api    = new UserApiStub($client);
$result = $api->getUser(1);

$this->assertArrayHasKey('id', $result);
```
