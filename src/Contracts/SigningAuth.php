<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

use Psr\Http\Message\RequestInterface;

/**
 * Marker interface for auth strategies that sign the full request
 * (e.g. AWS SigV4, HMAC, OAuth 1.0a).
 */
interface SigningAuth extends AuthStrategy
{
    /**
     * Returns the signature string for inspection / debugging purposes.
     */
    public function sign(RequestInterface $request): string;
}