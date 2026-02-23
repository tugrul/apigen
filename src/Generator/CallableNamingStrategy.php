<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

// ---------------------------------------------------------------------------
// Fully custom / callable strategy
// ---------------------------------------------------------------------------

/**
 * Accepts a callable for maximum flexibility.
 *
 * Example:
 *   new CallableNamingStrategy(function(ReflectionClass $iface, string $dir): array {
 *       return ['My\Custom\Namespace', $iface->getShortName() . 'HttpClient'];
 *   });
 */
final class CallableNamingStrategy implements StubNamingStrategy
{
    /** @param callable(ReflectionClass, string): array{string, string} $resolver */
    public function __construct(private readonly mixed $resolver)
    {
    }

    public function resolve(ReflectionClass $interface, string $outputDir): array
    {
        return ($this->resolver)($interface, $outputDir);
    }
}
