<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Generator;

use ReflectionClass;

/**
 * Resolves the correct output file path for a generated stub by understanding
 * how the source interface's namespace maps to its real directory on disk —
 * i.e. by discovering the PSR-4 mapping in effect for that class.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * WHY THIS IS NECESSARY
 * ─────────────────────────────────────────────────────────────────────────────
 * PSR-4 allows any namespace prefix to be rooted at any directory:
 *
 *   "autoload": { "psr-4": { "App\\": "src/" } }
 *
 * So App\Api\UserApi lives at  src/Api/UserApi.php
 *                                 ─── ← only THIS part is namespace-derived
 *
 * A naive str_replace('\\', '/', $namespace) would produce:
 *   outputDir/App/Api/UserApi.php   ← WRONG — double-nests the root prefix
 *
 * The correct output is:
 *   outputDir/Api/UserApi.php       ← strip the PSR-4 root prefix, keep the rest
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * HOW IT WORKS
 * ─────────────────────────────────────────────────────────────────────────────
 * Given a source interface and its generated stub namespace, the resolver:
 *
 *   1. Finds the interface's actual file path via ReflectionClass::getFileName().
 *   2. Compares the interface's namespace segments against its real directory
 *      structure from the right side, walking backwards through both until
 *      they diverge — that divergence point is the PSR-4 root prefix.
 *   3. Strips that same root prefix from the stub namespace.
 *   4. Returns outputDir / <relative-path> / ClassName.php
 *
 * This works with any PSR-4 setup, regardless of how many namespace segments
 * are collapsed into the root directory (e.g. "Vendor\\Pkg\\" → "src/").
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * FALLBACK BEHAVIOUR
 * ─────────────────────────────────────────────────────────────────────────────
 * If reflection cannot determine the file (e.g. eval'd class, PHAR, built-in),
 * the resolver falls back to flat placement: outputDir/ClassName.php
 * An optional $fallbackMode can be set to 'full_namespace' to use the old
 * naive full-namespace-as-path behaviour instead.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * COMPOSER INTEGRATION (OPTIONAL — more accurate)
 * ─────────────────────────────────────────────────────────────────────────────
 * If a composer.json path is supplied, the resolver reads the actual
 * autoload.psr-4 and autoload-dev.psr-4 maps directly for the most reliable
 * prefix stripping, bypassing reflection entirely for the root detection step.
 */
final class OutputPathResolver
{
    public const FALLBACK_FLAT           = 'flat';
    public const FALLBACK_FULL_NAMESPACE = 'full_namespace';

    /** @var array<string, string>|null  namespace-prefix → directory, from composer.json */
    private ?array $composerPsr4Map = null;

    public function __construct(
        private readonly string $outputDir,
        private readonly string $fallbackMode = self::FALLBACK_FLAT,
        ?string $composerJsonPath = null,
    ) {
        if ($composerJsonPath !== null) {
            $this->composerPsr4Map = $this->loadComposerPsr4($composerJsonPath);
        }
    }

    // ─── Public API ──────────────────────────────────────────────────────────

    /**
     * Compute the output file path for a generated stub.
     *
     * @param  ReflectionClass $sourceInterface  The interface being generated
     * @param  string          $stubNamespace    The stub's target namespace (from NamingStrategy)
     * @param  string          $stubShortName    The stub's short class name
     */
    public function resolve(
        ReflectionClass $sourceInterface,
        string $stubNamespace,
        string $stubShortName,
    ): string {
        $relPath = $this->computeRelativePath($sourceInterface, $stubNamespace);

        $base = rtrim($this->outputDir, '/\\');

        return $relPath !== ''
            ? "{$base}/{$relPath}/{$stubShortName}.php"
            : "{$base}/{$stubShortName}.php";
    }

    // ─── Core algorithm ──────────────────────────────────────────────────────

    private function computeRelativePath(ReflectionClass $sourceInterface, string $stubNamespace): string
    {
        $psr4Root = $this->detectPsr4Root($sourceInterface);

        if ($psr4Root === null) {
            // Cannot determine PSR-4 root — use configured fallback
            return $this->fallback($stubNamespace);
        }

        // Strip the PSR-4 root prefix from the stub namespace
        $relative = $this->stripNamespacePrefix($stubNamespace, $psr4Root);

        // Convert remaining namespace segments to directory separators
        return str_replace('\\', '/', $relative);
    }

    /**
     * Detect the PSR-4 namespace root prefix for a given class.
     * Returns the namespace prefix that maps to a filesystem directory root.
     * Returns null if detection fails.
     *
     * Detection priority:
     *   1. Composer PSR-4 map (if loaded) — most accurate
     *   2. Reflection-based heuristic — compares namespace segments with
     *      actual directory segments from the right
     */
    private function detectPsr4Root(ReflectionClass $rc): ?string
    {
        $fqcn = $rc->getName();

        // Strategy 1: composer.json map
        if ($this->composerPsr4Map !== null) {
            return $this->findComposerRoot($fqcn);
        }

        // Strategy 2: reflection heuristic
        return $this->detectRootFromReflection($rc);
    }

    // ─── Strategy 1: Composer PSR-4 map ──────────────────────────────────────

    private function findComposerRoot(string $fqcn): ?string
    {
        // Find the longest matching namespace prefix — most specific wins
        $bestPrefix = null;
        $bestLength = -1;

        foreach ($this->composerPsr4Map as $prefix => $dir) {
            // Normalise: ensure trailing backslash
            $prefix = rtrim($prefix, '\\') . '\\';

            if (str_starts_with($fqcn . '\\', $prefix)) {
                $len = strlen($prefix);
                if ($len > $bestLength) {
                    $bestLength = $len;
                    $bestPrefix = rtrim($prefix, '\\');
                }
            }
        }

        return $bestPrefix;
    }

    // ─── Strategy 2: Reflection heuristic ────────────────────────────────────

    /**
     * Walk namespace segments from the right, comparing them against the
     * corresponding directory segments of the real file path.
     * The first segment that diverges is the PSR-4 root boundary.
     *
     * Example:
     *   FQCN:      Vendor\Pkg\Api\UserApi
     *   File:      /project/src/Api/UserApi.php
     *   Dirs:      [project, src, Api]
     *   NS segs:   [Vendor, Pkg, Api]
     *
     *   Walking right-to-left:
     *     "Api"  == "Api"   → match, continue
     *     "Pkg"  == "src"   → DIVERGE → root prefix is "Vendor\Pkg"
     */
    private function detectRootFromReflection(ReflectionClass $rc): ?string
    {
        $filePath = $rc->getFileName();

        if ($filePath === false || $filePath === '') {
            return null;
        }

        $namespace = $rc->getNamespaceName();

        if ($namespace === '') {
            // Global namespace — no prefix to strip
            return '';
        }

        $nsSegments  = explode('\\', $namespace);
        $dirParts    = explode('/', str_replace('\\', '/', dirname($filePath)));

        // Remove the class file's directory from dir parts and compare from the right
        $nsCount  = count($nsSegments);
        $dirCount = count($dirParts);

        $matchedDepth = 0;

        for ($i = 0; $i < $nsCount; $i++) {
            $nsIdx  = $nsCount  - 1 - $i;   // walk NS from right
            $dirIdx = $dirCount - 1 - $i;   // walk dirs from right

            if ($dirIdx < 0) {
                break;
            }

            // Case-insensitive comparison handles Windows paths and
            // some creative PSR-4 setups that lowercase dir names
            if (strcasecmp($nsSegments[$nsIdx], $dirParts[$dirIdx]) === 0) {
                $matchedDepth++;
            } else {
                break;
            }
        }

        // The root prefix is everything BEFORE the matched suffix in the namespace
        $rootSegmentCount = $nsCount - $matchedDepth;

        if ($rootSegmentCount <= 0) {
            return '';
        }

        return implode('\\', array_slice($nsSegments, 0, $rootSegmentCount));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Strip a namespace prefix from a full namespace, returning only the tail.
     *
     * stripNamespacePrefix('Vendor\Pkg\Api\Users', 'Vendor\Pkg') → 'Api\Users'
     * stripNamespacePrefix('Vendor\Pkg', 'Vendor\Pkg')           → ''
     */
    private function stripNamespacePrefix(string $namespace, string $prefix): string
    {
        if ($prefix === '') {
            return $namespace;
        }

        $prefixWithSep = rtrim($prefix, '\\') . '\\';

        if (str_starts_with($namespace . '\\', $prefixWithSep)) {
            return ltrim(substr($namespace, strlen($prefix)), '\\');
        }

        // Stub namespace is in a different root than the source interface.
        // This happens when SubNamespaceNamingStrategy or CallableNamingStrategy
        // points to a completely different namespace tree.
        // Fall back: use entire stub namespace as relative path.
        return $this->fallback($namespace);
    }

    private function fallback(string $namespace): string
    {
        return match ($this->fallbackMode) {
            self::FALLBACK_FULL_NAMESPACE => str_replace('\\', '/', $namespace),
            default                       => '', // flat — put directly in outputDir root
        };
    }

    // ─── Composer loader ─────────────────────────────────────────────────────

    /**
     * Parse composer.json and return a merged psr-4 map from
     * autoload.psr-4 and autoload-dev.psr-4.
     *
     * Returns array<namespacePrefix, directoryPath>
     */
    private function loadComposerPsr4(string $composerJsonPath): array
    {
        if (!file_exists($composerJsonPath)) {
            throw new \InvalidArgumentException(
                "composer.json not found at [{$composerJsonPath}]"
            );
        }

        $data = json_decode(file_get_contents($composerJsonPath), true, 32, JSON_THROW_ON_ERROR);

        $map  = [];
        $base = dirname($composerJsonPath);

        foreach (['autoload', 'autoload-dev'] as $section) {
            foreach ($data[$section]['psr-4'] ?? [] as $prefix => $paths) {
                // paths can be a string or array of strings
                foreach ((array) $paths as $path) {
                    $absPath = $base . '/' . ltrim($path, '/');
                    $map[rtrim($prefix, '\\')] = rtrim($absPath, '/');
                }
            }
        }

        return $map;
    }

    // ─── Static factory helpers ───────────────────────────────────────────────

    /**
     * Create a resolver that auto-discovers composer.json by walking up from
     * the given start directory.
     */
    public static function withAutoDiscoveredComposer(
        string $outputDir,
        string $startDir,
        string $fallbackMode = self::FALLBACK_FLAT,
    ): self {
        $composerPath = self::findComposerJson($startDir);

        return new self($outputDir, $fallbackMode, $composerPath);
    }

    /**
     * Walk up the directory tree from $startDir to find composer.json.
     * Returns null if not found (e.g. running outside a Composer project).
     */
    public static function findComposerJson(string $startDir): ?string
    {
        $dir = realpath($startDir) ?: $startDir;

        while ($dir !== dirname($dir)) { // stop at filesystem root
            $candidate = $dir . DIRECTORY_SEPARATOR . 'composer.json';
            if (file_exists($candidate)) {
                return $candidate;
            }
            $dir = dirname($dir);
        }

        return null;
    }
}
