<?php

declare(strict_types=1);

namespace MyApp\Api;

use MyApp\Auth\GitHubTokenAuth;

use Tugrul\ApiGen\Attributes\Method\{GET, POST, PUT, DELETE};
use Tugrul\ApiGen\Attributes\Params\{Body, Header, Path, Query, QueryMap};
use Tugrul\ApiGen\Attributes\Modifiers\{ApiNamespace, NoAuth, Returns, StaticHeader, UseAuth};

/**
 * Declares the GitHub REST API surface.
 * The code generator will produce MyApp\Api\GitHubApiImpl.
 *
 * #[ApiNamespace] is optional — only needed if you use ProxyRegistry::fromAnnotated().
 */
#[ApiNamespace('github')]
#[StaticHeader('Accept', 'application/vnd.github+json')]
#[StaticHeader('X-GitHub-Api-Version', '2022-11-28')]
interface GitHubApi
{
    // --- Public endpoints (no auth required) ---

    #[GET('/repos/{owner}/{repo}')]
    #[Returns('array')]
    #[NoAuth]
    public function getRepository(
        #[Path] string $owner,
        #[Path] string $repo,
    ): array;

    #[GET('/repos/{owner}/{repo}/releases')]
    #[Returns('array')]
    #[NoAuth]
    public function listReleases(
        #[Path] string $owner,
        #[Path] string $repo,
        #[Query('per_page')] int $perPage = 30,
        #[Query] int $page = 1,
    ): array;

    // --- Authenticated endpoints ---

    #[GET('/user')]
    #[Returns('array')]
    public function getAuthenticatedUser(): array;

    #[GET('/user/repos')]
    #[Returns('array')]
    public function listMyRepositories(
        #[QueryMap] array $filters = [],
    ): array;

    #[POST('/user/repos')]
    #[Returns('array')]
    public function createRepository(
        #[Body] array $payload,
    ): array;

    #[PUT('/repos/{owner}/{repo}/contents/{path}')]
    #[Returns('array')]
    public function createOrUpdateFile(
        #[Path] string $owner,
        #[Path] string $repo,
        #[Path] string $path,
        #[Body] array $payload,
    ): array;

    #[DELETE('/repos/{owner}/{repo}')]
    public function deleteRepository(
        #[Path] string $owner,
        #[Path] string $repo,
    ): void;

    // --- Override auth per-method (e.g. use a fine-grained PAT for admin ops) ---

    #[GET('/repos/{owner}/{repo}/actions/secrets')]
    #[UseAuth(GitHubTokenAuth::class)]
    #[Returns('array')]
    public function listSecrets(
        #[Path] string $owner,
        #[Path] string $repo,
    ): array;

    // --- Dynamic header injection ---

    #[GET('/notifications')]
    #[Returns('array')]
    public function getNotifications(
        #[Header('X-Poll-Interval')] string $pollInterval = '60',
        #[Query] ?string $since = null,
    ): array;
}
