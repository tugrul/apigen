<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Fixtures\Auth;

// ---------------------------------------------------------------------------
// Fake auth class referenced by #[UseAuth] above
// ---------------------------------------------------------------------------

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

final class FakeAdminAuth implements AuthStrategy
{
    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('X-Admin', 'true');
    }
}
