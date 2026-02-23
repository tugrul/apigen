<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestInterface, ResponseInterface};

/**
 * Base test case providing PSR-7 factories and a configurable HTTP mock.
 */
abstract class ApiGenTestCase extends TestCase
{
    protected Psr17Factory $psr17;

    protected function setUp(): void
    {
        parent::setUp();
        $this->psr17 = new Psr17Factory();
    }

    /** Build a PSR-7 request for use in auth strategy tests. */
    protected function makeRequest(
        string $method = 'GET',
        string $uri = 'https://api.example.com/test',
        string $body = '',
    ): RequestInterface {
        $request = $this->psr17->createRequest($method, $uri);

        if ($body !== '') {
            $request = $request->withBody($this->psr17->createStream($body));
        }

        return $request;
    }

    /** Build a JSON PSR-7 response. */
    protected function makeJsonResponse(
        mixed $data,
        int $status = 200,
    ): ResponseInterface {
        $body = json_encode($data, JSON_THROW_ON_ERROR);

        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            $body,
        );
    }

    /** Build a mock HTTP client that returns a preset sequence of responses. */
    protected function mockHttpClient(ResponseInterface ...$responses): ClientInterface
    {
        return new class($responses) implements ClientInterface {
            private int $index = 0;
            /** @var RequestInterface[] */
            public array $capturedRequests = [];

            public function __construct(private array $responses) {}

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->capturedRequests[] = $request;

                if (!isset($this->responses[$this->index])) {
                    throw new \LogicException('Mock HTTP client ran out of responses.');
                }

                return $this->responses[$this->index++];
            }
        };
    }

    /** Assert a request has a header with the given value. */
    protected function assertRequestHeader(
        RequestInterface $request,
        string $headerName,
        string $expected,
    ): void {
        self::assertTrue(
            $request->hasHeader($headerName),
            "Request missing header [{$headerName}]",
        );
        self::assertSame(
            $expected,
            $request->getHeaderLine($headerName),
            "Header [{$headerName}] value mismatch",
        );
    }

    /** Assert a URI contains a query parameter with the given value. */
    protected function assertQueryParam(
        RequestInterface $request,
        string $paramName,
        string $expected,
    ): void {
        parse_str($request->getUri()->getQuery(), $params);
        self::assertArrayHasKey($paramName, $params, "Query param [{$paramName}] missing");
        self::assertSame($expected, $params[$paramName], "Query param [{$paramName}] value mismatch");
    }
}
