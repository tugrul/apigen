<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Fixtures;

use Tugrul\ApiGen\Attributes\Method\{GET, DELETE};
use Tugrul\ApiGen\Attributes\Params\Path;
use Tugrul\ApiGen\Attributes\Modifiers\Returns;


// ---------------------------------------------------------------------------
// Minimal interface — one GET, one void DELETE
// ---------------------------------------------------------------------------

interface SimpleApi
{
    #[GET('/users')]
    #[Returns('array')]
    public function listUsers(): array;

    #[DELETE('/users/{id}')]
    public function deleteUser(#[Path] int $id): void;
}
