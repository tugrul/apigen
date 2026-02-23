<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// In-memory (default — no persistence, survives only the current request)
// ---------------------------------------------------------------------------

namespace Tugrul\ApiGen\Auth\TokenCache;

use Tugrul\ApiGen\Auth\TokenCache;

final class InMemoryTokenCache implements TokenCache
{
    /** @var array<string, array{access_token: string, expires_at: int}> */
    private array $store = [];

    public function get(string $key): ?array
    {
        return $this->store[$key] ?? null;
    }

    public function set(string $key, array $entry): void
    {
        $this->store[$key] = $entry;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }
}
