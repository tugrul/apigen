<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

// ---------------------------------------------------------------------------
// HTTP Basic Auth
// ---------------------------------------------------------------------------

final class BasicAuth implements AuthStrategy
{
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    )
    {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $encoded = base64_encode("{$this->username}:{$this->password}");

        return $request->withHeader('Authorization', "Basic {$encoded}");
    }
}
