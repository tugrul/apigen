<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter as the request body (JSON encoded by default)
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Body
{
    public function __construct(public readonly string $encoding = 'json')
    {
    }
}
