<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Http;

use Tugrul\ApiGen\Contracts\AuthStrategy;
use Tugrul\ApiGen\Contracts\EndpointCall;

final class CallDescriptor implements EndpointCall
{
    private string          $method          = 'GET';
    private string          $path            = '/';
    private ?string         $baseUrlOverride = null;
    private ?string         $authClass       = null;
    private array           $pathParams      = [];
    private array           $queryParams     = [];
    private array           $headers         = [];
    private mixed           $body            = null;
    private string          $bodyEncoding    = 'json';
    private ?AuthStrategy   $auth            = null;
    private bool            $authDisabled    = false;
    private ?string         $returnType      = null;
    private ?string         $returnGenericOf = null;

    public static function create(): self
    {
        return new self();
    }

    // --- Fluent builder ---

    public function method(string $method): self
    {
        $this->method = strtoupper($method);

        return $this;
    }

    public function path(string $path): self
    {
        $this->path = $path;

        return $this;
    }

    public function pathParam(string $name, mixed $value): self
    {
        $this->pathParams[$name] = (string) $value;

        return $this;
    }

    public function queryParam(string $name, mixed $value): self
    {
        if ($value === null) {
            return $this;
        }

        $this->queryParams[$name] = $value;

        return $this;
    }

    public function queryMap(array $map): self
    {
        foreach ($map as $k => $v) {
            $this->queryParam($k, $v);
        }

        return $this;
    }

    public function header(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function headers(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->header($name, $value);
        }

        return $this;
    }

    public function body(mixed $body, string $encoding = 'json'): self
    {
        $this->body         = $body;
        $this->bodyEncoding = $encoding;

        return $this;
    }

    public function auth(AuthStrategy $auth): self
    {
        $this->auth = $auth;

        return $this;
    }

    public function baseUrl(string $url): self
    {
        $this->baseUrlOverride = $url;

        return $this;
    }

    public function useAuthClass(string $class): self
    {
        $this->authClass = $class;

        return $this;
    }

    public function disableAuth(): self
    {
        $this->authDisabled = true;

        return $this;
    }

    public function returnType(string $type, ?string $genericOf = null): self
    {
        $this->returnType      = $type;
        $this->returnGenericOf = $genericOf;

        return $this;
    }

    // --- EndpointCall contract ---

    public function getMethod(): string              { return $this->method; }
    public function getPath(): string                { return $this->path; }
    public function getBaseUrlOverride(): ?string    { return $this->baseUrlOverride; }
    public function getAuthClass(): ?string          { return $this->authClass; }
    public function getPathParams(): array           { return $this->pathParams; }
    public function getQueryParams(): array          { return $this->queryParams; }
    public function getHeaders(): array              { return $this->headers; }
    public function getBody(): mixed                 { return $this->body; }
    public function getBodyEncoding(): string        { return $this->bodyEncoding; }
    public function getAuth(): ?AuthStrategy         { return $this->auth; }
    public function isAuthDisabled(): bool           { return $this->authDisabled; }
    public function getReturnType(): ?string         { return $this->returnType; }
    public function getReturnGenericOf(): ?string    { return $this->returnGenericOf; }
}
