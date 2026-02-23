<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

// ---------------------------------------------------------------------------
// No-op (public APIs)
// ---------------------------------------------------------------------------

final class NoAuth implements AuthStrategy
{
    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request;
    }
}
