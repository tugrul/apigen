<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

// ---------------------------------------------------------------------------
// Smart default: same namespace + same class name; Impl suffix on conflict
// ---------------------------------------------------------------------------

/**
 * Default strategy — mirrors the interface exactly (same namespace, same name).
 * If a file with that class name already exists in the output directory AND
 * it is NOT a previously generated stub, falls back to the "Impl" suffix.
 *
 * Rule priority:
 *   1. Interface namespace + interface short name (if no conflict)
 *   2. Interface namespace + interface short name + "Impl" suffix (on conflict)
 */
final class DefaultNamingStrategy implements StubNamingStrategy
{
    /**
     * Returns [namespace, shortName] mirroring the source interface exactly.
     *
     * Conflict detection (appending "Impl" when a hand-written file exists at the
     * resolved output path) is handled by StubGenerator after the output path is
     * computed, since only StubGenerator has access to the OutputPathResolver.
     */
    public function resolve(ReflectionClass $interface, string $outputDir): array
    {
        return [
            $interface->getNamespaceName(),
            $interface->getShortName(),
        ];
    }
}
