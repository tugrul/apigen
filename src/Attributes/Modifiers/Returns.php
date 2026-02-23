<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Define the response type for code generation / deserialization hints
 *
 * #[Returns(UserDto::class)]
 * #[Returns('array', UserDto::class)]  // array of UserDto
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Returns
{
    public function __construct(
        public readonly string  $type,
        public readonly ?string $genericOf = null,
    )
    {
    }
}
