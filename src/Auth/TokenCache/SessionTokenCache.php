<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth\TokenCache;

// ---------------------------------------------------------------------------
// PHP native session cache
// ---------------------------------------------------------------------------

use Tugrul\ApiGen\Auth\TokenCache;

final class SessionTokenCache implements TokenCache
{
    public function __construct(private readonly string $sessionPrefix = '_apigen_token_')
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function get(string $key): ?array
    {
        $entry = $_SESSION[$this->sessionPrefix . $key] ?? null;

        if (!is_array($entry)) {
            return null;
        }

        return $entry;
    }

    public function set(string $key, array $entry): void
    {
        $_SESSION[$this->sessionPrefix . $key] = $entry;
    }

    public function delete(string $key): void
    {
        unset($_SESSION[$this->sessionPrefix . $key]);
    }
}
