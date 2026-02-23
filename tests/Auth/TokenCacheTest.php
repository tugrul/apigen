<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Auth;

use Tugrul\ApiGen\Auth\TokenCache\{FileTokenCache, InMemoryTokenCache};
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class TokenCacheTest extends ApiGenTestCase
{
    // ── InMemoryTokenCache ────────────────────────────────────────────────────

    public function test_in_memory_returns_null_for_unknown_key(): void
    {
        $cache = new InMemoryTokenCache();

        self::assertNull($cache->get('nonexistent'));
    }

    public function test_in_memory_stores_and_retrieves_entry(): void
    {
        $cache = new InMemoryTokenCache();
        $entry = ['access_token' => 'tok', 'expires_at' => time() + 3600];

        $cache->set('key', $entry);

        self::assertSame($entry, $cache->get('key'));
    }

    public function test_in_memory_delete_removes_entry(): void
    {
        $cache = new InMemoryTokenCache();
        $cache->set('key', ['access_token' => 'tok', 'expires_at' => time() + 3600]);
        $cache->delete('key');

        self::assertNull($cache->get('key'));
    }

    public function test_in_memory_delete_nonexistent_key_is_noop(): void
    {
        $cache = new InMemoryTokenCache();

        // Should not throw
        $cache->delete('missing');
        self::assertNull($cache->get('missing'));
    }

    public function test_in_memory_different_keys_are_isolated(): void
    {
        $cache = new InMemoryTokenCache();
        $e1 = ['access_token' => 'a', 'expires_at' => 1000];
        $e2 = ['access_token' => 'b', 'expires_at' => 2000];

        $cache->set('key1', $e1);
        $cache->set('key2', $e2);

        self::assertSame($e1, $cache->get('key1'));
        self::assertSame($e2, $cache->get('key2'));
    }

    // ── FileTokenCache ────────────────────────────────────────────────────────

    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/apigen_test_' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*') ?: []);
            rmdir($this->tmpDir);
        }
    }

    public function test_file_cache_creates_directory_on_construction(): void
    {
        new FileTokenCache($this->tmpDir);

        self::assertDirectoryExists($this->tmpDir);
    }

    public function test_file_cache_returns_null_for_unknown_key(): void
    {
        $cache = new FileTokenCache($this->tmpDir);

        self::assertNull($cache->get('no-such-key'));
    }

    public function test_file_cache_persists_and_retrieves_entry(): void
    {
        $cache = new FileTokenCache($this->tmpDir);
        $entry = ['access_token' => 'file-token', 'expires_at' => time() + 7200];

        $cache->set('mykey', $entry);
        $retrieved = $cache->get('mykey');

        self::assertSame($entry['access_token'], $retrieved['access_token']);
        self::assertSame($entry['expires_at'], $retrieved['expires_at']);
    }

    public function test_file_cache_delete_removes_file(): void
    {
        $cache = new FileTokenCache($this->tmpDir);
        $cache->set('k', ['access_token' => 't', 'expires_at' => 9999]);
        $cache->delete('k');

        self::assertNull($cache->get('k'));
        // No files should remain
        self::assertEmpty(glob($this->tmpDir . '/*.json'));
    }

    public function test_file_cache_delete_nonexistent_key_is_noop(): void
    {
        $cache = new FileTokenCache($this->tmpDir);

        // Should not throw
        $cache->delete('phantom');
        self::assertNull($cache->get('phantom'));
    }

    public function test_file_cache_different_keys_produce_different_files(): void
    {
        $cache = new FileTokenCache($this->tmpDir);
        $cache->set('k1', ['access_token' => 'a', 'expires_at' => 1]);
        $cache->set('k2', ['access_token' => 'b', 'expires_at' => 2]);

        self::assertCount(2, glob($this->tmpDir . '/*.json'));
        self::assertSame('a', $cache->get('k1')['access_token']);
        self::assertSame('b', $cache->get('k2')['access_token']);
    }

    public function test_file_cache_survives_new_instance_for_same_directory(): void
    {
        // Write with one instance
        (new FileTokenCache($this->tmpDir))->set('key', ['access_token' => 'persistent', 'expires_at' => 9999]);

        // Read with a fresh instance — simulates cross-request persistence
        $retrieved = (new FileTokenCache($this->tmpDir))->get('key');

        self::assertSame('persistent', $retrieved['access_token']);
    }
}
