<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

// ---------------------------------------------------------------------------
// Digest Auth stub (structure placeholder; full impl is RFC 7616 compliant)
// ---------------------------------------------------------------------------

final class DigestAuth implements AuthStrategy
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    )
    {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        // Real digest auth requires a challenge/response round-trip.
        // Subclass or decorate this to add the nonce negotiation layer
        // via a RetryMiddleware that catches 401 and re-sends with credentials.
        throw new \LogicException(
            'DigestAuth requires a middleware retry loop. ' .
            'Use DigestAuthMiddleware instead of injecting this directly.'
        );
    }
}
