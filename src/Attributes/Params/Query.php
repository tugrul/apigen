<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter to a query string parameter: ?page=1
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Query
{
    public function __construct(
        public readonly ?string $name = null,
        public readonly bool    $encoded = false,
    )
    {
    }
}
