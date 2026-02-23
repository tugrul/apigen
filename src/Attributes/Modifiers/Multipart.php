<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Mark the request body as multipart/form-data
 */
#[Attribute(Attribute::TARGET_METHOD)]
class Multipart
{
}
