<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth;

/**
 * Pluggable token cache for OAuth2ClientCredentialsAuth.
 *
 * Implement this interface to persist tokens in any store:
 * session, Redis, database, APCu, file system, etc.
 *
 * The cache key is supplied by OAuth2ClientCredentialsAuth so that
 * multiple auth instances (different client_ids or scopes) never
 * share the same slot.
 */
interface TokenCache
{
    /**
     * Return a cached token entry or null if absent / expired.
     *
     * @return array{access_token: string, expires_at: int}|null
     */
    public function get(string $key): ?array;

    /**
     * Persist a token entry.
     *
     * @param array{access_token: string, expires_at: int} $entry
     */
    public function set(string $key, array $entry): void;

    /** Explicitly remove a cached entry (e.g. on forced refresh). */
    public function delete(string $key): void;
}
