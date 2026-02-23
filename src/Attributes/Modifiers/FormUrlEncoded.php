<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Mark the request body as application/x-www-form-urlencoded
 */
#[Attribute(Attribute::TARGET_METHOD)]
class FormUrlEncoded
{
}
