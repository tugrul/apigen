<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\TokenCache;

// ---------------------------------------------------------------------------
// PSR-16 (SimpleCache) bridge — covers Redis, Memcached, APCu, Doctrine, …
// ---------------------------------------------------------------------------

use Psr\SimpleCache\CacheInterface;
use Tugrul\ApiGen\Auth\TokenCache;

final class Psr16TokenCache implements TokenCache
{
    /**
     * @param CacheInterface $cache Any PSR-16 implementation
     * @param string|null $keyPrefix
     * @param int $ttlBuffer Extra seconds to shave off the real TTL so we
     *                            never hand the cache a token that's about to expire.
     */
    public function __construct(
        private readonly \Psr\SimpleCache\CacheInterface $cache,
        private readonly ?string                         $keyPrefix = 'apigen_token_',
        private readonly int                             $ttlBuffer = 30,
    )
    {
    }

    public function get(string $key): ?array
    {
        $entry = $this->cache->get($this->keyPrefix . $key);

        return is_array($entry) ? $entry : null;
    }

    public function set(string $key, array $entry): void
    {
        $ttl = max(1, ($entry['expires_at'] - time()) - $this->ttlBuffer);
        $this->cache->set($this->keyPrefix . $key, $entry, $ttl);
    }

    public function delete(string $key): void
    {
        $this->cache->delete($this->keyPrefix . $key);
    }
}
