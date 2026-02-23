<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

// ---------------------------------------------------------------------------
// Bearer token (OAuth 2 access tokens, JWT, etc.)
// ---------------------------------------------------------------------------

final class BearerTokenAuth implements AuthStrategy
{
    public function __construct(private string $token)
    {
    }

    public function withToken(string $token): self
    {
        $clone = clone $this;
        $clone->token = $token;

        return $clone;
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', "Bearer {$this->token}");
    }
}
