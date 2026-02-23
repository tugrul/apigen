<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// File-system cache (single JSON file per cache key)
// ---------------------------------------------------------------------------

namespace Tugrul\ApiGen\Auth\TokenCache;

use Tugrul\ApiGen\Auth\TokenCache;

final class FileTokenCache implements TokenCache
{
    public function __construct(
        private readonly string $directory,
        private readonly int    $filePerm = 0600,
    )
    {
        if (!is_dir($directory)) {
            mkdir($directory, 0700, recursive: true);
        }
    }

    public function get(string $key): ?array
    {
        $path = $this->path($key);

        if (!file_exists($path)) {
            return null;
        }

        $raw = file_get_contents($path);

        if ($raw === false) {
            return null;
        }

        try {
            $entry = json_decode($raw, true, 3, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($entry) ? $entry : null;
    }

    public function set(string $key, array $entry): void
    {
        $path = $this->path($key);
        file_put_contents($path, json_encode($entry, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        chmod($path, $this->filePerm);
    }

    public function delete(string $key): void
    {
        $path = $this->path($key);

        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function path(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'token_' . hash('sha256', $key) . '.json';
    }
}
