<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter to a URI path segment: /users/{id}
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Path
{
    public function __construct(public readonly ?string $name = null) {}
}

