<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Modifiers;

use Attribute;

/**
 * Skip default authorization for this endpoint
 */
#[Attribute(Attribute::TARGET_METHOD)]
class NoAuth
{
}
