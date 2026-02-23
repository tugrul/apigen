<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter as the full query map: ['page' => 1, 'limit' => 10]
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class QueryMap
{
    public function __construct(public readonly bool $encoded = false)
    {
    }
}
