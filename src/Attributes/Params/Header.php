<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter to a specific request header
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Header
{
    public function __construct(public readonly string $name)
    {
    }
}
