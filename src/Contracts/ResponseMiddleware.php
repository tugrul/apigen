<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

use Psr\Http\Message\{RequestInterface, ResponseInterface};

/**
 * Optional middleware hook — runs after the response is received.
 */
interface ResponseMiddleware
{
    public function after(
        RequestInterface  $request,
        ResponseInterface $response,
    ): ResponseInterface;
}