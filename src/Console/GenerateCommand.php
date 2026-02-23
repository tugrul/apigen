<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Console;

use Tugrul\ApiGen\Generator\OutputPathResolver;
use Tugrul\ApiGen\Generator\{
    StubGenerator,
    StubNamingStrategy,
    DefaultNamingStrategy,
    SuffixNamingStrategy,
    SubNamespaceNamingStrategy,
    CallableNamingStrategy,
};

/**
 * CLI command: apigen generate
 *
 * Generates stub/implementation classes from annotated PHP interfaces.
 *
 * Usage:
 *   apigen generate <InterfaceClass> [options]
 *   apigen generate --config=apigen.php
 *
 * Options:
 *   --output, -o        Output directory (default: ./generated)
 *   --naming            Naming strategy: default | suffix:<Suffix> | subns:<Namespace>
 *   --composer          Path to composer.json for PSR-4 map (auto-discovered if omitted)
 *   --path-mode         Fallback path mode when PSR-4 root cannot be detected:
 *                         flat (default) — put file directly in outputDir
 *                         full_namespace — old naive namespace-as-path behaviour
 *   --config, -c        Path to a PHP config file (overrides all other options)
 *   --dry-run           Print what would be generated without writing files
 *   --force, -f         Overwrite existing files without asking
 *   --verbose, -v       Print detailed information
 */
final class GenerateCommand
{
    public function __construct(private readonly Output $output) {}

    public function run(array $argv): int
    {
        $args    = $this->parseArgs($argv);
        $dryRun  = isset($args['dry-run']);
        $verbose = isset($args['verbose']) || isset($args['v']);

        // --- Load config file if provided ---
        if (isset($args['config']) || isset($args['c'])) {
            return $this->runFromConfig($args['config'] ?? $args['c'], $dryRun, $verbose);
        }

        // --- Collect interface classes from positional args ---
        $interfaces = array_filter($args['_'] ?? [], fn($v) => str_contains($v, '\\') || class_exists($v));

        if (empty($interfaces)) {
            $this->output->error('No interface class(es) specified.');
            $this->printUsage();

            return 1;
        }

        $outputDir    = $args['output'] ?? $args['o'] ?? getcwd() . '/generated';
        $naming       = $this->resolveNaming($args['naming'] ?? 'default');
        $pathResolver = $this->buildPathResolver($args, $outputDir);

        return $this->doGenerate($interfaces, $outputDir, $naming, $dryRun, $verbose, $pathResolver);
    }

    // --- Config-file mode ---

    private function runFromConfig(string $configPath, bool $dryRun, bool $verbose): int
    {
        if (!file_exists($configPath)) {
            $this->output->error("Config file not found: {$configPath}");

            return 1;
        }

        $config = require $configPath;

        if (!is_array($config)) {
            $this->output->error("Config file must return an array.");

            return 1;
        }

        $interfaces = (array) ($config['interfaces'] ?? []);
        $outputDir  = $config['output_dir'] ?? getcwd() . '/generated';
        $naming     = $config['naming'] ?? new DefaultNamingStrategy();

        if (is_string($naming)) {
            $naming = $this->resolveNaming($naming);
        }

        if (!$naming instanceof StubNamingStrategy) {
            $this->output->error('config[naming] must be a string or StubNamingStrategy instance.');

            return 1;
        }

        // Support class-level autoload bootstrap from config
        if (isset($config['bootstrap']) && is_string($config['bootstrap'])) {
            require_once $config['bootstrap'];
        }

        $pathResolver = null;
        if (isset($config['composer_json'])) {
            $mode         = $config['path_fallback_mode'] ?? OutputPathResolver::FALLBACK_FLAT;
            $pathResolver = new OutputPathResolver($outputDir, $mode, $config['composer_json']);
        } elseif (isset($config['path_fallback_mode'])) {
            $pathResolver = OutputPathResolver::withAutoDiscoveredComposer(
                $outputDir,
                dirname($configPath),
                $config['path_fallback_mode'],
            );
        }

        return $this->doGenerate($interfaces, $outputDir, $naming, $dryRun, $verbose, $pathResolver);
    }

    // --- Core generation logic ---

    private function doGenerate(
        array $interfaces,
        string $outputDir,
        StubNamingStrategy $naming,
        bool $dryRun,
        bool $verbose,
        ?OutputPathResolver $pathResolver = null,
    ): int {
        $this->output->info("Output directory : {$outputDir}");
        $this->output->info("Naming strategy  : " . $naming::class);
        $this->output->info("Path resolver    : " . ($pathResolver ? $pathResolver::class : 'auto (PSR-4 discovery)'));
        $this->output->info("Dry run          : " . ($dryRun ? 'yes' : 'no'));
        $this->output->line('');

        $generator = new StubGenerator($outputDir, $naming, $pathResolver);
        $errors    = 0;

        foreach ($interfaces as $interface) {
            if (!interface_exists($interface)) {
                $this->output->error("  ✗  [{$interface}] is not a loadable interface.");
                $errors++;
                continue;
            }

            try {
                if ($dryRun) {
                    $rc      = new \ReflectionClass($interface);
                    [$ns, $cls] = $naming->resolve($rc, $outputDir);
                    $fqcn    = ($ns !== '' ? $ns . '\\' : '') . $cls;
                    $this->output->success("  ~  {$interface}  →  {$fqcn}  [dry-run]");
                    continue;
                }

                $stub = $generator->generate($interface);

                $this->output->success("  ✓  {$interface}");

                if ($verbose) {
                    $this->output->dim("       → class : {$stub->stubClass}");
                    $this->output->dim("       → file  : {$stub->filePath}");
                }
            } catch (\Throwable $e) {
                $this->output->error("  ✗  {$interface} — {$e->getMessage()}");

                if ($verbose) {
                    $this->output->dim($e->getTraceAsString());
                }

                $errors++;
            }
        }

        $total = count($interfaces);
        $ok    = $total - $errors;
        $this->output->line('');
        $this->output->info("Done. {$ok}/{$total} generated successfully." . ($errors > 0 ? " {$errors} failed." : ''));

        return $errors > 0 ? 1 : 0;
    }

    // --- Path resolver factory ---

    private function buildPathResolver(array $args, string $outputDir): ?OutputPathResolver
    {
        $composerPath = $args['composer'] ?? null;
        $fallbackMode = $args['path-mode'] ?? OutputPathResolver::FALLBACK_FLAT;

        if ($composerPath !== null) {
            return new OutputPathResolver($outputDir, $fallbackMode, $composerPath);
        }

        // Always auto-discover unless explicitly disabled
        return OutputPathResolver::withAutoDiscoveredComposer($outputDir, getcwd() ?: $outputDir, $fallbackMode);
    }

    // --- Naming strategy factory ---

    private function resolveNaming(string $spec): StubNamingStrategy
    {
        if ($spec === 'default') {
            return new DefaultNamingStrategy();
        }

        if (str_starts_with($spec, 'suffix:')) {
            return new SuffixNamingStrategy(substr($spec, 7));
        }

        if (str_starts_with($spec, 'subns:')) {
            return new SubNamespaceNamingStrategy(substr($spec, 6));
        }

        if (str_starts_with($spec, 'subns+suffix:')) {
            // e.g. "subns+suffix:Generated:Stub"
            [$subns, $suffix] = explode(':', substr($spec, 13), 2) + ['Generated', 'Stub'];

            return new SubNamespaceNamingStrategy($subns, $suffix);
        }

        $this->output->error("Unknown naming strategy: [{$spec}]. Use: default | suffix:<S> | subns:<NS> | subns+suffix:<NS>:<S>");

        return new DefaultNamingStrategy();
    }

    // --- Arg parser ---

    private function parseArgs(array $argv): array
    {
        $result = ['_' => []];

        for ($i = 0; $i < count($argv); $i++) {
            $arg = $argv[$i];

            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$key, $val] = explode('=', $arg, 2);
                    $result[$key] = $val;
                } else {
                    $next = $argv[$i + 1] ?? null;
                    if ($next !== null && !str_starts_with($next, '-')) {
                        $result[$arg] = $next;
                        $i++;
                    } else {
                        $result[$arg] = true;
                    }
                }
            } elseif (str_starts_with($arg, '-') && strlen($arg) === 2) {
                $key  = $arg[1];
                $next = $argv[$i + 1] ?? null;
                if ($next !== null && !str_starts_with($next, '-')) {
                    $result[$key] = $next;
                    $i++;
                } else {
                    $result[$key] = true;
                }
            } else {
                $result['_'][] = $arg;
            }
        }

        return $result;
    }

    private function printUsage(): void
    {
        $this->output->line(<<<'USAGE'

        Usage:
          apigen generate <InterfaceClass> [InterfaceClass2 ...] [options]
          apigen generate --config=apigen.php [options]

        Options:
          --output, -o <dir>        Output directory (default: ./generated)
          --naming <strategy>       Naming strategy:
                                      default              (same name as interface, Impl on conflict)
                                      suffix:<Suffix>      (e.g. suffix:Stub → UserApiStub)
                                      subns:<Namespace>    (e.g. subns:Generated → MyApp\Api\Generated\UserApi)
                                      subns+suffix:<NS>:<S> (combines both)
          --composer <file>         Path to composer.json (auto-discovered by default)
          --path-mode <mode>        Fallback when PSR-4 root is undeterminable:
                                      flat (default) — place directly in --output dir
                                      full_namespace — use full namespace as sub-path
          --config, -c <file>       PHP config file path (see apigen.php.example)
          --dry-run                 Preview without writing files
          --force, -f               Overwrite existing non-generated files
          --verbose, -v             Show generated file paths

        USAGE);
    }
}
