<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Add static query parameters to every request on a class or method
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class StaticQuery
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    )
    {
    }
}
