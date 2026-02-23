<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Mark the interface as a namespacing proxy (optional grouping feature)
 *
 * #[ApiNamespace('users')]
 */
#[Attribute(Attribute::TARGET_CLASS)]
class ApiNamespace
{
    public function __construct(public readonly string $prefix)
    {
    }
}