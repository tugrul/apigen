<?php

declare(strict_types=1);

namespace MyApp\Auth;

use Psr\Http\Message\RequestInterface;
use Tugrul\ApiGen\Contracts\AuthStrategy;

/**
 * Example: GitHub Personal Access Token auth.
 * Referenced by #[UseAuth(GitHubTokenAuth::class)] in the interface.
 */
final class GitHubTokenAuth implements AuthStrategy
{
    public function __construct(
        private readonly string $token = '', // injected by AuthResolver registry
    ) {}

    public function authenticate(RequestInterface $request): RequestInterface
    {
        return $request->withHeader('Authorization', "Bearer {$this->token}");
    }
}
