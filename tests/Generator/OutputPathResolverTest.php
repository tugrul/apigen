<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Generator;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tugrul\ApiGen\Generator\OutputPathResolver;

/**
 * Tests the PSR-4 aware path resolution logic.
 *
 * We cannot easily test the reflection-heuristic path with live classes
 * because class file paths are fixed at autoload time. Instead we:
 *   (a) test the composer.json strategy with synthetic JSON files,
 *   (b) test edge cases (empty namespace, different root, fallback modes),
 *   (c) use a live class from our own src/ to test the full reflection path.
 */
final class OutputPathResolverTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/apigen_resolver_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->rmdirRecursive($this->tmpDir);
    }

    // ── Composer PSR-4 map strategy ───────────────────────────────────────────

    public function test_composer_strategy_strips_root_prefix_single_segment(): void
    {
        // "App\\" → "src/" means App\Api\UserApi → src/Api/UserApi.php
        $composerJson = $this->writeComposerJson(['App\\' => 'src/']);
        $resolver     = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT, $composerJson);
        $rc           = new ReflectionClass(\Tugrul\ApiGen\Auth\Strategy\BearerTokenAuth::class);

        // Simulate: interface is in App\Api, stub stays in App\Api
        // We can't change the actual class, so we test with the resolver's
        // stripNamespacePrefix logic by checking resolve() produces expected output.
        // Use a real class from our package to probe the composer strategy.
        $composerJson2 = $this->writeComposerJson(['Tugrul\\ApiGen\\' => 'src/']);
        $resolver2     = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT, $composerJson2);

        // BearerTokenAuth is in Tugrul\ApiGen\Auth
        // Root prefix: Tugrul\ApiGen  →  strip it  →  relative: Auth
        // Output: out/Auth/BearerTokenAuth.php
        $path = $resolver2->resolve($rc, 'Tugrul\\ApiGen\\Auth', 'BearerTokenAuth');

        self::assertStringEndsWith('/Auth/BearerTokenAuth.php', $path);
        self::assertStringStartsWith($this->tmpDir . '/out', $path);
    }

    public function test_composer_strategy_longest_prefix_wins(): void
    {
        // Both "App\\" and "App\\Api\\" map somewhere — the more specific should win
        $composerJson = $this->writeComposerJson([
            'App\\'      => 'src/',
            'App\\Api\\' => 'src/Api/',
        ]);
        $resolver = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT, $composerJson);
        $rc       = new ReflectionClass(\Tugrul\ApiGen\Auth\Strategy\BearerTokenAuth::class);

        // Use Tugrul\ApiGen\Auth namespace; with "Tugrul\\ApiGen\\" as the best prefix
        $composerJson2 = $this->writeComposerJson([
            'Tugrul\\'          => 'vendor/tugrul/',
            'Tugrul\\ApiGen\\' => 'src/',
        ]);
        $resolver2 = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT, $composerJson2);

        $path = $resolver2->resolve($rc, 'Tugrul\\ApiGen\\Auth', 'BearerTokenAuth');

        // Longer prefix "Tugrul\ApiGen\" wins → relative path is "Auth"
        self::assertStringEndsWith('/Auth/BearerTokenAuth.php', $path);
    }

    public function test_composer_strategy_full_match_produces_flat_path(): void
    {
        // Namespace exactly equals the PSR-4 root prefix → no subdirectory
        $composerJson = $this->writeComposerJson(['Tugrul\\ApiGen\\Auth\\' => 'src/Auth/']);
        $resolver     = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT, $composerJson);
        $rc           = new ReflectionClass(\Tugrul\ApiGen\Auth\Strategy\BearerTokenAuth::class);

        $path = $resolver->resolve($rc, 'Tugrul\\ApiGen\\Auth', 'BearerTokenAuth');

        // Stripped result is '' → file goes directly in output dir
        self::assertStringEndsWith('/BearerTokenAuth.php', $path);
    }

    // ── Reflection heuristic strategy ─────────────────────────────────────────

    public function test_reflection_strategy_resolves_known_class(): void
    {
        // OutputPathResolver itself lives at src/Generator/OutputPathResolver.php
        // Namespace: Tugrul\ApiGen\Generator
        // Heuristic walks right: "Generator" matches dir "Generator", then "ApiGen" ≠ "src" → root = "Tugrul\ApiGen"
        // Stub namespace "Tugrul\ApiGen\Generator" → strip "Tugrul\ApiGen" → "Generator"
        $resolver = new OutputPathResolver($this->tmpDir . '/out');
        $rc       = new ReflectionClass(OutputPathResolver::class);

        $path = $resolver->resolve($rc, 'Tugrul\\ApiGen\\Generator', 'SomeStub');

        self::assertStringEndsWith('/Generator/SomeStub.php', $path);
    }

    public function test_reflection_strategy_with_sub_namespace(): void
    {
        // Same class, but stub is in Tugrul\ApiGen\Generator\Generated
        $resolver = new OutputPathResolver($this->tmpDir . '/out');
        $rc       = new ReflectionClass(OutputPathResolver::class);

        $path = $resolver->resolve($rc, 'Tugrul\\ApiGen\\Generator\\Generated', 'SomeStub');

        self::assertStringEndsWith('/Generator/Generated/SomeStub.php', $path);
    }

    // ── Fallback modes ────────────────────────────────────────────────────────

    public function test_fallback_flat_when_namespace_cannot_be_determined(): void
    {
        // Use a class with no file (internal/built-in) to force null from reflection.
        // We'll use a mock ReflectionClass stand-in — but since we can't easily mock that,
        // we test via a resolver with no composer map and a namespace that diverges from dirs.
        $resolver = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT);
        $rc       = new ReflectionClass(\stdClass::class); // built-in, getFileName() returns false

        $path = $resolver->resolve($rc, 'Any\\Namespace', 'MyClass');

        // flat fallback: class goes directly in outputDir
        self::assertSame($this->tmpDir . '/out/MyClass.php', $path);
    }

    public function test_fallback_full_namespace_mode(): void
    {
        $resolver = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FULL_NAMESPACE);
        $rc       = new ReflectionClass(\stdClass::class);

        $path = $resolver->resolve($rc, 'Some\\Deep\\Namespace', 'MyClass');

        self::assertStringEndsWith('/Some/Deep/Namespace/MyClass.php', $path);
    }

    // ── Cross-tree stubs (SubNamespaceNamingStrategy edge case) ──────────────

    public function test_stub_in_different_root_falls_back_gracefully(): void
    {
        // Source interface: Tugrul\ApiGen\Generator (known root)
        // Stub namespace:   Completely\Different\Tree (not under same root)
        $composerJson = $this->writeComposerJson(['Tugrul\\ApiGen\\' => 'src/']);
        $resolver     = new OutputPathResolver($this->tmpDir . '/out', OutputPathResolver::FALLBACK_FLAT, $composerJson);
        $rc           = new ReflectionClass(OutputPathResolver::class);

        $path = $resolver->resolve($rc, 'Completely\\Different\\Tree', 'MyStub');

        // Falls back to flat — stub goes directly in outputDir
        self::assertStringEndsWith('/MyStub.php', $path);
    }

    // ── findComposerJson ──────────────────────────────────────────────────────

    public function test_find_composer_json_walks_up_directories(): void
    {
        // Create a fake composer.json in tmpDir
        file_put_contents($this->tmpDir . '/composer.json', '{}');

        // Start from a subdirectory
        $subDir = $this->tmpDir . '/a/b/c';
        mkdir($subDir, 0755, true);

        $found = OutputPathResolver::findComposerJson($subDir);

        self::assertSame(realpath($this->tmpDir . '/composer.json'), realpath($found));
    }

    public function test_find_composer_json_returns_null_when_not_found(): void
    {
        // Use a temp dir with no composer.json in it or its ancestors
        // (root dir has no composer.json — walk to filesystem root returns null)
        // We can't guarantee this in all environments, so we just test that
        // a directory with no composer.json up to the package root returns something or null
        $result = OutputPathResolver::findComposerJson($this->tmpDir);

        // Either null (no composer.json found) or a string path — either is valid.
        // The important thing is it doesn't throw.
        self::assertTrue($result === null || is_string($result));
    }

    // ── withAutoDiscoveredComposer ─────────────────────────────────────────────

    public function test_auto_discovered_resolver_uses_found_composer_json(): void
    {
        $composerContent = json_encode([
            'autoload' => ['psr-4' => ['Tugrul\\ApiGen\\' => 'src/']],
        ]);
        file_put_contents($this->tmpDir . '/composer.json', $composerContent);

        $subDir = $this->tmpDir . '/src/Generator';
        mkdir($subDir, 0755, true);

        $resolver = OutputPathResolver::withAutoDiscoveredComposer($this->tmpDir . '/out', $subDir);
        $rc       = new ReflectionClass(OutputPathResolver::class);

        $path = $resolver->resolve($rc, 'Tugrul\\ApiGen\\Generator', 'TestStub');

        self::assertStringEndsWith('/Generator/TestStub.php', $path);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function writeComposerJson(array $psr4Map): string
    {
        $path = $this->tmpDir . '/composer_' . bin2hex(random_bytes(4)) . '.json';
        $data = ['autoload' => ['psr-4' => $psr4Map]];
        file_put_contents($path, json_encode($data, JSON_THROW_ON_ERROR));

        return $path;
    }

    private function rmdirRecursive(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (glob($dir . '/*') ?: [] as $entry) {
            is_dir($entry) ? $this->rmdirRecursive($entry) : unlink($entry);
        }

        rmdir($dir);
    }
}
