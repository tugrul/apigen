<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Set a single static header. Stackable.
 *
 * #[StaticHeader('Accept', 'application/json')]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class StaticHeader
{
    public function __construct(
        public readonly string $name,
        public readonly string $value,
    )
    {
    }
}
