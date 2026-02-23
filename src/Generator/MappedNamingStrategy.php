<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

// ---------------------------------------------------------------------------
// Per-interface explicit map — useful in batch generation scripts
// ---------------------------------------------------------------------------

/**
 * Resolves naming from a static map keyed by interface FQCN.
 * Falls back to a delegate strategy for unmapped interfaces.
 *
 * Example:
 *   new MappedNamingStrategy([
 *       UserApi::class    => ['My\Generated', 'UserApiClient'],
 *       ProductApi::class => ['My\Generated', 'ProductApiClient'],
 *   ]);
 */
final class MappedNamingStrategy implements StubNamingStrategy
{
    /**
     * @param array<class-string, array{string, string}> $map interface → [namespace, className]
     */
    public function __construct(
        private readonly array              $map,
        private readonly StubNamingStrategy $fallback = new DefaultNamingStrategy(),
    )
    {
    }

    public function resolve(ReflectionClass $interface, string $outputDir): array
    {
        return $this->map[$interface->getName()] ?? $this->fallback->resolve($interface, $outputDir);
    }
}
