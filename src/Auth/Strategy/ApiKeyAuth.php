<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\Strategy;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

// ---------------------------------------------------------------------------
// API Key (header or query param)
// ---------------------------------------------------------------------------

final class ApiKeyAuth implements AuthStrategy
{
    public const LOCATION_HEADER = 'header';
    public const LOCATION_QUERY = 'query';

    public function __construct(
        private readonly string $key,
        private readonly string $name = 'X-Api-Key',
        private readonly string $location = self::LOCATION_HEADER,
    )
    {
    }

    public function authenticate(RequestInterface $request): RequestInterface
    {
        if ($this->location === self::LOCATION_QUERY) {
            $uri = $request->getUri();
            $existing = $uri->getQuery();
            $pair = urlencode($this->name) . '=' . urlencode($this->key);
            $query = $existing !== '' ? "{$existing}&{$pair}" : $pair;

            return $request->withUri($uri->withQuery($query));
        }

        return $request->withHeader($this->name, $this->key);
    }
}