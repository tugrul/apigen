<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

use Psr\Http\Message\RequestInterface;

/**
 * Core contract every auth strategy must implement.
 * Receives the outgoing PSR-7 request and returns a (possibly modified) request.
 */
interface AuthStrategy
{
    /**
     * Authenticate the request.
     * Implementations may add headers, query params, sign the body, etc.
     */
    public function authenticate(RequestInterface $request): RequestInterface;
}
