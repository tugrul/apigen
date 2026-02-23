<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Auth;

use Tugrul\ApiGen\Contracts\{AuthStrategy, EndpointCall};

/**
 * Resolves the effective AuthStrategy for a given endpoint call.
 *
 * Priority:
 *   1. #[NoAuth]                         → no authentication
 *   2. #[UseAuth(SomeStrategy::class)]   → method-level override
 *   3. Call carries an explicit strategy  → caller-provided
 *   4. Client default strategy           → fallback
 *   5. null                              → nothing applied
 */
final class AuthResolver
{
    /** @param array<string, AuthStrategy> $registry named strategies for #[UseAuth] lookups */
    public function __construct(
        private array $registry = [],
    ) {}

    public function resolve(EndpointCall $call, ?AuthStrategy $clientDefault): ?AuthStrategy
    {
        if ($call->isAuthDisabled()) {
            return null;
        }

        if ($call->getAuth() !== null) {
            return $call->getAuth();
        }

        return $clientDefault;
    }

    /**
     * Instantiate or retrieve a strategy by class name.
     * Used by generated stubs when #[UseAuth(SomeClass::class)] is present.
     */
    public function fromClass(string $class): AuthStrategy
    {
        if (isset($this->registry[$class])) {
            return $this->registry[$class];
        }

        if (!class_exists($class)) {
            throw new \InvalidArgumentException("Auth strategy class [{$class}] not found.");
        }

        $strategy = new $class();

        if (!$strategy instanceof AuthStrategy) {
            throw new \InvalidArgumentException(
                "[{$class}] must implement " . AuthStrategy::class
            );
        }

        return $strategy;
    }

    public function withStrategy(string $key, AuthStrategy $strategy): self
    {
        $clone = clone $this;
        $clone->registry[$key] = $strategy;

        return $clone;
    }
}
