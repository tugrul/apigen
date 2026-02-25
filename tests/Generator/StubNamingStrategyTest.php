<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Generator;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Tugrul\ApiGen\Generator\{CallableNamingStrategy, DefaultNamingStrategy, MappedNamingStrategy,
    SubNamespaceNamingStrategy, SuffixNamingStrategy, StubGenerator};
use Tugrul\ApiGen\Tests\Fixtures\{PetApi, SimpleApi};

final class StubNamingStrategyTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/apigen_naming_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*') ?: []);
            rmdir($this->tmpDir);
        }
    }

    private function rc(string $class): ReflectionClass
    {
        return new ReflectionClass($class);
    }

    // ── DefaultNamingStrategy ─────────────────────────────────────────────────

    public function test_default_returns_same_namespace_as_interface(): void
    {
        [$ns] = (new DefaultNamingStrategy())->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('Tugrul\\ApiGen\\Tests\\Fixtures', $ns);
    }

    public function test_default_returns_same_short_name_as_interface(): void
    {
        [, $cls] = (new DefaultNamingStrategy())->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('SimpleApi', $cls);
    }

    public function test_default_works_for_different_interface(): void
    {
        [$ns, $cls] = (new DefaultNamingStrategy())->resolve($this->rc(PetApi::class), $this->tmpDir);

        self::assertSame('Tugrul\\ApiGen\\Tests\\Fixtures', $ns);
        self::assertSame('PetApi', $cls);
    }

    // Conflict detection (file-exists → Impl suffix) is intentionally handled in
    // StubGenerator after the output path is resolved, not inside the naming strategy.
    // See StubGeneratorTest::test_impl_suffix_used_when_file_exists_and_is_not_generated().

    // ── SuffixNamingStrategy ──────────────────────────────────────────────────

    public function test_suffix_stub_appended_to_class_name(): void
    {
        [, $cls] = (new SuffixNamingStrategy('Stub'))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('SimpleApiStub', $cls);
    }

    public function test_suffix_client_appended(): void
    {
        [, $cls] = (new SuffixNamingStrategy('Client'))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('SimpleApiClient', $cls);
    }

    public function test_suffix_preserves_interface_namespace_by_default(): void
    {
        [$ns] = (new SuffixNamingStrategy('Stub'))->resolve($this->rc(PetApi::class), $this->tmpDir);

        self::assertSame('Tugrul\\ApiGen\\Tests\\Fixtures', $ns);
    }

    public function test_suffix_with_namespace_override(): void
    {
        [$ns, $cls] = (new SuffixNamingStrategy('Stub', 'My\\Clients'))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('My\\Clients',    $ns);
        self::assertSame('SimpleApiStub', $cls);
    }

    public function test_suffix_empty_string_leaves_name_unchanged(): void
    {
        [, $cls] = (new SuffixNamingStrategy(''))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('SimpleApi', $cls);
    }

    // ── SubNamespaceNamingStrategy ─────────────────────────────────────────────

    public function test_sub_namespace_appended_to_interface_namespace(): void
    {
        [$ns] = (new SubNamespaceNamingStrategy('Generated'))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('Tugrul\\ApiGen\\Tests\\Fixtures\\Generated', $ns);
    }

    public function test_sub_namespace_preserves_class_name(): void
    {
        [, $cls] = (new SubNamespaceNamingStrategy('Generated'))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('SimpleApi', $cls);
    }

    public function test_sub_namespace_with_suffix(): void
    {
        [$ns, $cls] = (new SubNamespaceNamingStrategy('Generated', 'Impl'))->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('Tugrul\\ApiGen\\Tests\\Fixtures\\Generated', $ns);
        self::assertSame('SimpleApiImpl', $cls);
    }

    public function test_sub_namespace_with_global_namespace_class(): void
    {
        // stdClass has no namespace — sub-namespace becomes the entire namespace
        [$ns, $cls] = (new SubNamespaceNamingStrategy('Gen'))->resolve(new ReflectionClass(\stdClass::class), $this->tmpDir);

        self::assertSame('Gen',     $ns);
        self::assertSame('stdClass', $cls);
    }

    // ── CallableNamingStrategy ────────────────────────────────────────────────

    public function test_callable_receives_reflection_class_and_output_dir(): void
    {
        $capturedRc  = null;
        $capturedDir = null;

        $strategy = new CallableNamingStrategy(function (ReflectionClass $rc, string $dir) use (&$capturedRc, &$capturedDir) {
            $capturedRc  = $rc;
            $capturedDir = $dir;

            return ['Custom\\NS', 'CustomClass'];
        });

        $strategy->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertInstanceOf(ReflectionClass::class, $capturedRc);
        self::assertSame(SimpleApi::class, $capturedRc->getName());
        self::assertSame($this->tmpDir, $capturedDir);
    }

    public function test_callable_returns_exactly_what_closure_returns(): void
    {
        $strategy   = new CallableNamingStrategy(fn($rc, $dir) => ['NS', $rc->getShortName() . 'Http']);
        [$ns, $cls] = $strategy->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('NS',            $ns);
        self::assertSame('SimpleApiHttp', $cls);
    }

    // ── MappedNamingStrategy ──────────────────────────────────────────────────

    public function test_mapped_returns_explicit_entry_for_known_interface(): void
    {
        $strategy   = new MappedNamingStrategy([SimpleApi::class => ['My\\Layer', 'SimpleClient']]);
        [$ns, $cls] = $strategy->resolve($this->rc(SimpleApi::class), $this->tmpDir);

        self::assertSame('My\\Layer',    $ns);
        self::assertSame('SimpleClient', $cls);
    }

    public function test_mapped_uses_fallback_for_unmapped_interface(): void
    {
        $strategy   = new MappedNamingStrategy(
            [SimpleApi::class => ['A', 'B']],
            fallback: new SuffixNamingStrategy('Stub'),
        );
        [, $cls] = $strategy->resolve($this->rc(PetApi::class), $this->tmpDir);

        self::assertSame('PetApiStub', $cls); // PetApi not in map → fallback used
    }

    public function test_mapped_default_fallback_is_default_naming_strategy(): void
    {
        // Empty map → DefaultNamingStrategy used → same name as interface
        [, $cls] = (new MappedNamingStrategy([]))->resolve($this->rc(PetApi::class), $this->tmpDir);

        self::assertSame('PetApi', $cls);
    }

    public function test_mapped_with_multiple_entries(): void
    {
        $strategy = new MappedNamingStrategy([
            SimpleApi::class => ['NS1', 'ClassA'],
            PetApi::class    => ['NS2', 'ClassB'],
        ]);

        [, $clsA] = $strategy->resolve($this->rc(SimpleApi::class), $this->tmpDir);
        [, $clsB] = $strategy->resolve($this->rc(PetApi::class), $this->tmpDir);

        self::assertSame('ClassA', $clsA);
        self::assertSame('ClassB', $clsB);
    }

    public function test_omit_suffix(): void
    {
        $this->assertSame('Api', StubGenerator::omitSuffix('ApiInterface', 'Interface'));

        $this->assertSame('Api', StubGenerator::omitSuffix('Api', 'Interface'));

        $this->assertSame('Interface', StubGenerator::omitSuffix('Interface', 'Interface'));
    }
}
