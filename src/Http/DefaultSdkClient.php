<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\{RequestFactoryInterface,
    RequestInterface,
    ResponseInterface,
    StreamFactoryInterface,
    UriFactoryInterface};
use Tugrul\ApiGen\Auth\AuthResolver;
use Tugrul\ApiGen\Contracts\{AuthStrategy, EndpointCall, RequestMiddleware, ResponseDecoder,
    ResponseMiddleware, SdkClient};
use Tugrul\ApiGen\Exception\ApiException;

final class DefaultSdkClient implements SdkClient
{
    /** @var RequestMiddleware[] */
    private array $requestMiddleware = [];

    /** @var ResponseMiddleware[] */
    private array $responseMiddleware = [];

    public function __construct(
        private readonly ClientInterface          $httpClient,
        private readonly RequestFactoryInterface  $requestFactory,
        private readonly StreamFactoryInterface   $streamFactory,
        private readonly UriFactoryInterface      $uriFactory,
        private readonly string                   $baseUrl,
        private readonly ?AuthStrategy            $defaultAuth = null,
        private readonly ?ResponseDecoder         $decoder = null,
        private readonly AuthResolver             $authResolver = new AuthResolver(),
    ) {}

    // --- SdkClient ---

    public function getHttpClient(): ClientInterface              { return $this->httpClient; }
    public function getRequestFactory(): RequestFactoryInterface  { return $this->requestFactory; }
    public function getStreamFactory(): StreamFactoryInterface    { return $this->streamFactory; }
    public function getUriFactory(): UriFactoryInterface         { return $this->uriFactory; }
    public function getBaseUrl(): string                         { return $this->baseUrl; }
    public function getDefaultAuth(): ?AuthStrategy              { return $this->defaultAuth; }

    // --- Middleware registration ---

    public function addRequestMiddleware(RequestMiddleware $mw): self
    {
        $this->requestMiddleware[] = $mw;

        return $this;
    }

    public function addResponseMiddleware(ResponseMiddleware $mw): self
    {
        $this->responseMiddleware[] = $mw;

        return $this;
    }

    // --- Execute ---

    public function execute(EndpointCall $call): mixed
    {
        $request = $this->buildRequest($call);
        $request = $this->applyAuth($request, $call);
        $request = $this->runRequestMiddleware($request);

        $response = $this->httpClient->sendRequest($request);
        $response = $this->runResponseMiddleware($request, $response);

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 400) {
            throw new ApiException(
                "API request failed with HTTP {$statusCode}",
                $statusCode,
                $request,
                $response,
            );
        }

        $body = (string) $response->getBody();

        if ($body === '') {
            return null;
        }

        if ($this->decoder !== null) {
            return $this->decoder->decode($body, $call->getReturnType(), $call->getReturnGenericOf());
        }

        return $this->defaultDecode($body, $call->getReturnType());
    }

    // --- Request builder ---

    private function buildRequest(EndpointCall $call): RequestInterface
    {
        $path = $this->resolvePath($call);
        $url  = rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');

        $uri = $this->uriFactory->createUri($url);

        if ($call->getQueryParams()) {
            $uri = $uri->withQuery(http_build_query($call->getQueryParams()));
        }

        $request = $this->requestFactory->createRequest($call->getMethod(), $uri);

        foreach ($call->getHeaders() as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        if ($call->getBody() !== null) {
            [$contentType, $bodyStream] = $this->encodeBody($call);
            $request = $request
                ->withHeader('Content-Type', $contentType)
                ->withBody($this->streamFactory->createStream($bodyStream));
        }

        return $request;
    }

    private function resolvePath(EndpointCall $call): string
    {
        $path = $call->getPath();

        foreach ($call->getPathParams() as $name => $value) {
            $path = str_replace('{' . $name . '}', rawurlencode($value), $path);
        }

        return $path;
    }

    private function encodeBody(EndpointCall $call): array
    {
        return match ($call->getBodyEncoding()) {
            'json'      => ['application/json', json_encode($call->getBody(), JSON_THROW_ON_ERROR)],
            'form'      => ['application/x-www-form-urlencoded', http_build_query((array) $call->getBody())],
            'raw'       => ['application/octet-stream', (string) $call->getBody()],
            'multipart' => $this->buildMultipart($call->getBody()),
            default     => ['application/json', json_encode($call->getBody(), JSON_THROW_ON_ERROR)],
        };
    }

    private function buildMultipart(mixed $parts): array
    {
        $boundary = '----TugrulApiGenBoundary' . bin2hex(random_bytes(8));
        $body     = '';

        foreach ((array) $parts as $name => $value) {
            $body .= "--{$boundary}\r\n";
            $body .= "Content-Disposition: form-data; name=\"{$name}\"\r\n\r\n";
            $body .= $value . "\r\n";
        }

        $body .= "--{$boundary}--\r\n";

        return ["multipart/form-data; boundary={$boundary}", $body];
    }

    // --- Auth ---

    private function applyAuth(RequestInterface $request, EndpointCall $call): RequestInterface
    {
        $strategy = $this->authResolver->resolve($call, $this->defaultAuth);

        return $strategy ? $strategy->authenticate($request) : $request;
    }

    // --- Middleware ---

    private function runRequestMiddleware(RequestInterface $request): RequestInterface
    {
        foreach ($this->requestMiddleware as $mw) {
            $request = $mw->before($request);
        }

        return $request;
    }

    private function runResponseMiddleware(RequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        foreach ($this->responseMiddleware as $mw) {
            $response = $mw->after($request, $response);
        }

        return $response;
    }

    // --- Default decoder ---

    private function defaultDecode(string $body, ?string $type): mixed
    {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if ($type === null || $type === 'array' || $type === 'mixed') {
            return $data;
        }

        if (class_exists($type) && is_array($data)) {
            return new $type(...$data);
        }

        return $data;
    }
}
