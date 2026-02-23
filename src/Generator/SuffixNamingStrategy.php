<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

// ---------------------------------------------------------------------------
// Suffix strategy — appends a fixed suffix (classic "Stub", "Client", etc.)
// ---------------------------------------------------------------------------

/**
 * Appends a fixed suffix to the interface name.
 * Namespace stays the same as the interface by default.
 *
 * Examples:
 *   suffix: 'Stub'   → UserApi → UserApiStub
 *   suffix: 'Client' → UserApi → UserApiClient
 *   suffix: 'Impl'   → UserApi → UserApiImpl
 */
final class SuffixNamingStrategy implements StubNamingStrategy
{
    public function __construct(
        private readonly string  $suffix = 'Stub',
        private readonly ?string $overrideNamespace = null,
    )
    {
    }

    public function resolve(ReflectionClass $interface, string $outputDir): array
    {
        $namespace = $this->overrideNamespace ?? $interface->getNamespaceName();
        $shortName = $interface->getShortName() . $this->suffix;

        return [$namespace, $shortName];
    }
}