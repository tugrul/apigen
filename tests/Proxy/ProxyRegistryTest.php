<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Proxy;

use Tugrul\ApiGen\Attributes\Modifiers\ApiNamespace;
use Tugrul\ApiGen\Contracts\SdkClient;
use Tugrul\ApiGen\Http\CallDescriptor;
use Tugrul\ApiGen\Proxy\ProxyRegistry;
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class ProxyRegistryTest extends ApiGenTestCase
{
    private SdkClient $client;

    protected function setUp(): void
    {
        parent::setUp();

        // Minimal SdkClient stub — ProxyRegistry only stores and constructs stubs
        $this->client = $this->createMock(SdkClient::class);
    }

    // ── fromStubs ─────────────────────────────────────────────────────────────

    public function test_from_stubs_registers_and_retrieves(): void
    {
        $stub    = new \stdClass();
        $registry = ProxyRegistry::fromStubs($this->client, ['users' => $stub]);

        self::assertSame($stub, $registry->get('users'));
    }

    public function test_get_throws_for_unknown_name(): void
    {
        $registry = ProxyRegistry::fromStubs($this->client, []);

        $this->expectException(\OutOfBoundsException::class);
        $this->expectExceptionMessageMatches('/unknown/');

        $registry->get('unknown');
    }

    public function test_magic_property_access(): void
    {
        $stub     = new \stdClass();
        $registry = ProxyRegistry::fromStubs($this->client, ['orders' => $stub]);

        self::assertSame($stub, $registry->orders);
    }

    public function test_isset_returns_true_for_registered_name(): void
    {
        $registry = ProxyRegistry::fromStubs($this->client, ['x' => new \stdClass()]);

        self::assertTrue(isset($registry->x));
        self::assertFalse(isset($registry->y));
    }

    public function test_registered_names_returns_all_keys(): void
    {
        $registry = ProxyRegistry::fromStubs($this->client, [
            'users'    => new \stdClass(),
            'products' => new \stdClass(),
        ]);

        self::assertSame(['users', 'products'], $registry->registeredNames());
    }

    public function test_register_method_adds_stub(): void
    {
        $registry = ProxyRegistry::fromStubs($this->client, []);
        $stub     = new \stdClass();
        $registry->register('late', $stub);

        self::assertSame($stub, $registry->get('late'));
    }

    // ── fromAnnotated ─────────────────────────────────────────────────────────

    public function test_from_annotated_reads_api_namespace_from_interface(): void
    {
        // AnnotatedStub implements an interface decorated with #[ApiNamespace('pets')]
        $registry = ProxyRegistry::fromAnnotated($this->client, [AnnotatedStub::class]);

        self::assertInstanceOf(AnnotatedStub::class, $registry->get('pets'));
    }

    public function test_from_annotated_throws_when_no_api_namespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/ApiNamespace/');

        ProxyRegistry::fromAnnotated($this->client, [NoNamespaceStub::class]);
    }

    // ── Multiple stubs ────────────────────────────────────────────────────────

    public function test_multiple_stubs_independently_accessible(): void
    {
        $s1 = new \stdClass();
        $s2 = new \stdClass();

        $registry = ProxyRegistry::fromStubs($this->client, ['a' => $s1, 'b' => $s2]);

        self::assertSame($s1, $registry->a);
        self::assertSame($s2, $registry->b);
    }
}

// ---------------------------------------------------------------------------
// Inline test stubs used by fromAnnotated tests
// ---------------------------------------------------------------------------

#[ApiNamespace('pets')]
interface AnnotatedInterface
{
    public function dummy(): void;
}

final class AnnotatedStub implements AnnotatedInterface
{
    public function __construct(SdkClient $client) {}

    public function dummy(): void {}
}

interface NoNamespaceInterface
{
    public function dummy(): void;
}

final class NoNamespaceStub implements NoNamespaceInterface
{
    public function __construct(SdkClient $client) {}

    public function dummy(): void {}
}
