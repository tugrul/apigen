<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;


// ---------------------------------------------------------------------------
// Literal / static token in a header
// ---------------------------------------------------------------------------

final class StaticTokenAuth implements AuthStrategy
{
    public function __construct(
        private readonly string $token,
        private readonly string $headerName = 'Authorization',
        private readonly string $prefix = '',
    )
    {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        $value = $this->prefix !== '' ? "{$this->prefix} {$this->token}" : $this->token;

        return $request->withHeader($this->headerName, $value);
    }
}
