<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Contracts;

/**
 * Marker interface for auth strategies that need refresh / token lifecycle.
 */
interface RefreshableAuth extends AuthStrategy
{
    public function refresh(): void;

    public function isExpired(): bool;
}
