<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Fixtures\Util;

class OptionalParamObject
{
    public function __construct(
        public readonly string $name,
        public readonly int $age = 30,
    ) {}
}
