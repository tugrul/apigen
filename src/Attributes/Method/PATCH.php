<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Method;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class PATCH
{
    public function __construct(public readonly string $path)
    {
    }
}