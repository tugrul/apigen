<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Fixtures\Util;

class SetStateObject
{
    public string $name;
    public int $value;

    public static function __set_state(array $props): static
    {
        $obj        = new static();
        $obj->name  = $props['name'];
        $obj->value = $props['value'];
        return $obj;
    }
}
