<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

// ---------------------------------------------------------------------------
// Sub-namespace strategy — places stubs in a child namespace
// ---------------------------------------------------------------------------

/**
 * Appends a sub-namespace segment to the interface's namespace.
 * Class name stays identical to the interface name (no suffix).
 *
 * Example:
 *   interface MyApp\Api\UserApi
 *   subNamespace: 'Generated'
 *   → MyApp\Api\Generated\UserApi
 *
 * Combine with a suffix by using SuffixNamingStrategy as a delegate or
 * use CustomNamingStrategy for full control.
 */
final class SubNamespaceNamingStrategy implements StubNamingStrategy
{
    public function __construct(
        private readonly string $subNamespace,
        private readonly string $suffix = '',
    )
    {
    }

    public function resolve(ReflectionClass $interface, string $outputDir): array
    {
        $base = $interface->getNamespaceName();
        $namespace = $base !== '' ? $base . '\\' . $this->subNamespace : $this->subNamespace;
        $shortName = $interface->getShortName() . $this->suffix;

        return [$namespace, $shortName];
    }
}