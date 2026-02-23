<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Override authorization strategy for this specific endpoint
 *
 * #[UseAuth(ApiKeyAuth::class)]
 */
#[Attribute(Attribute::TARGET_METHOD)]
class UseAuth
{
    public function __construct(public readonly string $authClass)
    {
    }
}
