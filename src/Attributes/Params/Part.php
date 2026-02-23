<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter as a multipart file part
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Part
{
    public function __construct(public readonly ?string $name = null)
    {
    }
}
