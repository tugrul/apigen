<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

use Psr\Http\Message\RequestInterface;

/**
 * Optional middleware hook — runs before the request is sent.
 */
interface RequestMiddleware
{
    public function before(RequestInterface $request): RequestInterface;
}
