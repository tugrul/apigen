<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

/**
 * Immutable value object describing a generated stub file.
 * Returned by StubGenerator::generate() / generateAll().
 */
final class GeneratedStub
{
    public function __construct(
        /** The source interface FQCN */
        public string $interfaceClass,
        /** Fully-qualified class name of the generated class */
        public string $stubClass,
        /** Short (unqualified) class name */
        public string $stubShortName,
        /** Namespace of the generated class */
        public string $stubNamespace,
        /** Absolute path to the written PHP file */
        public string $filePath,
    ) {}

    public function __toString(): string
    {
        return $this->stubClass;
    }
}
