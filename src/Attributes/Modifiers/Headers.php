<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Set static headers on a method or interface. Stackable.
 *
 * #[Headers(['Accept' => 'application/json', 'X-Api-Version' => '2'])]
 */
#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
class Headers
{
    public function __construct(public readonly array $headers) {}
}
