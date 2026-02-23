<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Tests\Auth;

use Tugrul\ApiGen\Auth\AuthResolver;
use Tugrul\ApiGen\Auth\Strategy\{BearerTokenAuth, NoAuth};
use Tugrul\ApiGen\Http\CallDescriptor;
use Tugrul\ApiGen\Tests\ApiGenTestCase;

final class AuthResolverTest extends ApiGenTestCase
{
    private AuthResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AuthResolver();
    }

    // ── #[NoAuth] → null regardless of client default ─────────────────────────

    public function test_disabled_auth_returns_null(): void
    {
        $call   = CallDescriptor::create()->disableAuth();
        $result = $this->resolver->resolve($call, new BearerTokenAuth('tok'));

        self::assertNull($result);
    }

    // ── Explicit strategy on call takes priority over client default ───────────

    public function test_call_auth_takes_priority_over_client_default(): void
    {
        $callAuth   = new BearerTokenAuth('call-level');
        $clientAuth = new BearerTokenAuth('client-level');

        $call   = CallDescriptor::create()->auth($callAuth);
        $result = $this->resolver->resolve($call, $clientAuth);

        self::assertSame($callAuth, $result);
    }

    // ── Falls back to client default ─────────────────────────────────────────

    public function test_falls_back_to_client_default(): void
    {
        $clientAuth = new BearerTokenAuth('default');
        $call       = CallDescriptor::create();

        $result = $this->resolver->resolve($call, $clientAuth);

        self::assertSame($clientAuth, $result);
    }

    public function test_returns_null_when_no_auth_anywhere(): void
    {
        $call   = CallDescriptor::create();
        $result = $this->resolver->resolve($call, null);

        self::assertNull($result);
    }

    // ── fromClass ─────────────────────────────────────────────────────────────

    public function test_from_class_returns_registered_strategy(): void
    {
        $strategy = new BearerTokenAuth('tok');
        $resolver = $this->resolver->withStrategy('my-key', $strategy);

        $result = $resolver->fromClass('my-key');

        self::assertSame($strategy, $result);
    }

    public function test_from_class_instantiates_unregistered_class(): void
    {
        $result = $this->resolver->fromClass(NoAuth::class);

        self::assertInstanceOf(NoAuth::class, $result);
    }

    public function test_from_class_throws_for_nonexistent_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/NonExistentClass/');

        $this->resolver->fromClass('NonExistentClass');
    }

    public function test_from_class_throws_when_class_does_not_implement_auth_strategy(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/must implement/');

        $this->resolver->fromClass(\stdClass::class);
    }

    // ── withStrategy immutability ─────────────────────────────────────────────

    public function test_with_strategy_returns_new_instance(): void
    {
        $resolver2 = $this->resolver->withStrategy('k', new NoAuth());

        self::assertNotSame($this->resolver, $resolver2);
    }

    public function test_original_resolver_unaffected_after_with_strategy(): void
    {
        $this->resolver->withStrategy('k', new NoAuth());

        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->fromClass('k');
    }
}
