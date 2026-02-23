<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Override the base URL for this specific method
 */
#[Attribute(Attribute::TARGET_METHOD)]
class BaseUrl
{
    public function __construct(public readonly string $url)
    {
    }
}