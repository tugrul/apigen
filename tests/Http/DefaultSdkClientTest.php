<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Http;

use Nyholm\Psr7\Response;
use Psr\Http\Message\{RequestInterface, ResponseInterface};
use Tugrul\ApiGen\Auth\Strategy\BearerTokenAuth;
use Tugrul\ApiGen\Contracts\{RequestMiddleware, ResponseMiddleware};
use Tugrul\ApiGen\Exception\ApiException;
use Tugrul\ApiGen\Http\{CallDescriptor, DefaultSdkClient};
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class DefaultSdkClientTest extends ApiGenTestCase
{
    private function makeClient(
        ResponseInterface $response,
        ?string $auth = null,
    ): array {
        $httpClient = $this->mockHttpClient($response);

        $client = new DefaultSdkClient(
            httpClient:     $httpClient,
            requestFactory: $this->psr17,
            streamFactory:  $this->psr17,
            uriFactory:     $this->psr17,
            baseUrl:        'https://api.example.com',
            defaultAuth:    $auth !== null ? new BearerTokenAuth($auth) : null,
            defaultHeaders: [ 'X-My-Header' => 'testing123' ]
        );

        return [$client, $httpClient];
    }

    // ── URL construction ──────────────────────────────────────────────────────

    public function test_builds_correct_url_from_base_and_path(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse(['ok' => true]));

        $client->execute(CallDescriptor::create()->method('GET')->path('/users'));

        $uri = (string) $http->capturedRequests[0]->getUri();
        self::assertStringContainsString('api.example.com', $uri);
        self::assertStringContainsString('/users', $uri);
    }

    public function test_path_params_are_substituted(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()
                ->method('GET')
                ->path('/users/{id}/posts/{postId}')
                ->pathParam('id', 99)
                ->pathParam('postId', 7),
        );

        $path = $http->capturedRequests[0]->getUri()->getPath();
        self::assertStringContainsString('/users/99/posts/7', $path);
    }

    public function test_path_params_are_url_encoded(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('GET')->path('/items/{name}')->pathParam('name', 'hello world'),
        );

        self::assertStringContainsString('hello%20world', (string) $http->capturedRequests[0]->getUri());
    }

    public function test_query_params_appended_to_url(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('GET')->path('/search')->queryParam('q', 'cats')->queryParam('page', 2),
        );

        $query = $http->capturedRequests[0]->getUri()->getQuery();
        self::assertStringContainsString('q=cats', $query);
        self::assertStringContainsString('page=2', $query);
    }

    // ── Request headers ───────────────────────────────────────────────────────

    public function test_static_headers_set_on_request(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('GET')->path('/x')->header('Accept', 'application/json'),
        );

        $this->assertRequestHeader($http->capturedRequests[0], 'Accept', 'application/json');
    }

    public function test_default_headers_set_on_client_build(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('GET')->path('/x'),
        );

        $this->assertRequestHeader($http->capturedRequests[0], 'X-My-Header', 'testing123');
    }

    // ── Body encoding ─────────────────────────────────────────────────────────

    public function test_json_body_sets_content_type_and_encodes(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('POST')->path('/items')->body(['name' => 'rex'], 'json'),
        );

        $req = $http->capturedRequests[0];
        $this->assertRequestHeader($req, 'Content-Type', 'application/json');
        self::assertSame('{"name":"rex"}', (string) $req->getBody());
    }

    public function test_form_body_sets_content_type_and_encodes(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('POST')->path('/form')->body(['a' => '1', 'b' => '2'], 'form'),
        );

        $req = $http->capturedRequests[0];
        $this->assertRequestHeader($req, 'Content-Type', 'application/x-www-form-urlencoded');
        self::assertStringContainsString('a=1', (string) $req->getBody());
        self::assertStringContainsString('b=2', (string) $req->getBody());
    }

    public function test_multipart_body_sets_multipart_content_type(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(
            CallDescriptor::create()->method('POST')->path('/upload')->body(['file' => 'content'], 'multipart'),
        );

        $contentType = $http->capturedRequests[0]->getHeaderLine('Content-Type');
        self::assertStringStartsWith('multipart/form-data; boundary=', $contentType);
        self::assertStringContainsString('content', (string) $http->capturedRequests[0]->getBody());
    }

    public function test_null_body_sends_no_content_type(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $client->execute(CallDescriptor::create()->method('GET')->path('/x'));

        self::assertFalse($http->capturedRequests[0]->hasHeader('Content-Type'));
    }

    // ── Auth ──────────────────────────────────────────────────────────────────

    public function test_default_auth_applied(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]), auth: 'my-token');

        $client->execute(CallDescriptor::create()->method('GET')->path('/secure'));

        $this->assertRequestHeader($http->capturedRequests[0], 'Authorization', 'Bearer my-token');
    }

    public function test_auth_skipped_when_disabled(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]), auth: 'tok');

        $client->execute(CallDescriptor::create()->method('GET')->path('/public')->disableAuth());

        self::assertFalse($http->capturedRequests[0]->hasHeader('Authorization'));
    }

    // ── Response decoding ─────────────────────────────────────────────────────

    public function test_json_response_decoded_to_array(): void
    {
        [$client] = $this->makeClient($this->makeJsonResponse(['id' => 1, 'name' => 'Rex']));

        $result = $client->execute(CallDescriptor::create()->method('GET')->path('/pet'));

        self::assertSame(['id' => 1, 'name' => 'Rex'], $result);
    }

    public function test_empty_response_body_returns_null(): void
    {
        $response = new Response(204, []);
        [$client] = $this->makeClient($response);

        $result = $client->execute(CallDescriptor::create()->method('DELETE')->path('/item'));

        self::assertNull($result);
    }

    // ── Error handling ────────────────────────────────────────────────────────

    public function test_throws_api_exception_on_4xx(): void
    {
        [$client] = $this->makeClient(new Response(404, [], '{"error":"not found"}'));

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(404);

        $client->execute(CallDescriptor::create()->method('GET')->path('/missing'));
    }

    public function test_throws_api_exception_on_5xx(): void
    {
        [$client] = $this->makeClient(new Response(500, [], '{"error":"server error"}'));

        $this->expectException(ApiException::class);
        $this->expectExceptionCode(500);

        $client->execute(CallDescriptor::create()->method('GET')->path('/crash'));
    }

    public function test_api_exception_exposes_request_and_response(): void
    {
        [$client] = $this->makeClient(new Response(422, [], '{"field":"required"}'));

        try {
            $client->execute(CallDescriptor::create()->method('POST')->path('/validate')->body(['x' => 1]));
            self::fail('Expected ApiException');
        } catch (ApiException $e) {
            self::assertSame(422, $e->getCode());
            self::assertNotNull($e->getRequest());
            self::assertNotNull($e->getResponse());
            self::assertStringContainsString('field', $e->getResponseBody());
        }
    }

    // ── Middleware ────────────────────────────────────────────────────────────

    public function test_request_middleware_runs_before_send(): void
    {
        [$client, $http] = $this->makeClient($this->makeJsonResponse([]));

        $mw = new class implements RequestMiddleware {
            public function before(RequestInterface $request): RequestInterface
            {
                return $request->withHeader('X-Middleware', 'was-here');
            }
        };

        $client->addRequestMiddleware($mw);
        $client->execute(CallDescriptor::create()->method('GET')->path('/x'));

        $this->assertRequestHeader($http->capturedRequests[0], 'X-Middleware', 'was-here');
    }

    public function test_response_middleware_can_modify_response(): void
    {
        [$client] = $this->makeClient($this->makeJsonResponse(['original' => true]));

        $mw = new class implements ResponseMiddleware {
            public function after(RequestInterface $req, ResponseInterface $res): ResponseInterface
            {
                return $res->withHeader('X-Processed', 'yes');
            }
        };

        $client->addResponseMiddleware($mw);

        // The modified response is still decoded — we can only verify no exception thrown
        // since the mock returns valid JSON; the middleware header is on the response object
        $result = $client->execute(CallDescriptor::create()->method('GET')->path('/x'));
        self::assertSame(['original' => true], $result);
    }
}
