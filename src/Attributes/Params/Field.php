<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Attributes\Params;

use Attribute;

/**
 * Bind a method parameter as a form field (requires FormUrlEncoded or Multipart on method)
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
class Field
{
    public function __construct(public readonly ?string $name = null)
    {
    }
}
