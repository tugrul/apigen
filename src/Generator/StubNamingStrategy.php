<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

/**
 * Determines the stub class name and namespace for a given interface.
 *
 * Three built-in strategies are provided. Developers can also implement
 * this interface directly for full control.
 */
interface StubNamingStrategy
{
    /**
     * Return [fullyQualifiedNamespace, shortClassName] for the generated stub.
     *
     * @param ReflectionClass $interface The interface being generated
     * @param string          $outputDir The configured output directory (for conflict detection)
     */
    public function resolve(ReflectionClass $interface, string $outputDir): array;
}
