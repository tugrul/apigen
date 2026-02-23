<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Exception;

use Psr\Http\Message\{RequestInterface, ResponseInterface};

final class ApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code,
        private readonly RequestInterface  $request,
        private readonly ResponseInterface $response,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function getRequest(): RequestInterface   { return $this->request; }
    public function getResponse(): ResponseInterface { return $this->response; }

    public function getResponseBody(): string
    {
        return (string) $this->response->getBody();
    }
}
