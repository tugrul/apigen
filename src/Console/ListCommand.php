<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Console;

use Tugrul\ApiGen\Attributes\Method\{GET, POST, PUT, PATCH, DELETE, HEAD};

/**
 * CLI command: apigen list
 *
 * Scans a directory for PHP files, loads them, and lists all interfaces
 * that have at least one HTTP attribute. Useful for discovering which
 * interfaces are ready for code generation.
 *
 * Usage:
 *   apigen list [--dir=<path>] [--verbose]
 */
final class ListCommand
{
    private const HTTP_ATTRS = [GET::class, POST::class, PUT::class, PATCH::class, DELETE::class, HEAD::class];

    public function __construct(private readonly Output $output) {}

    public function run(array $argv): int
    {
        $args    = $this->parseArgs($argv);
        $dir     = $args['dir'] ?? $args['d'] ?? getcwd() . '/src';
        $verbose = isset($args['verbose']) || isset($args['v']);

        if (!is_dir($dir)) {
            $this->output->error("Directory not found: {$dir}");

            return 1;
        }

        $this->output->header("Scanning: {$dir}");

        $phpFiles  = $this->findPhpFiles($dir);
        $found     = [];

        foreach ($phpFiles as $file) {
            $before = get_declared_interfaces();
            @require_once $file;
            $after = get_declared_interfaces();

            $new = array_diff($after, $before);

            foreach ($new as $iface) {
                $rc = new \ReflectionClass($iface);

                foreach ($rc->getMethods() as $method) {
                    $hasHttp = false;
                    foreach (self::HTTP_ATTRS as $attrClass) {
                        if (!empty($method->getAttributes($attrClass))) {
                            $hasHttp = true;
                            break;
                        }
                    }
                    if ($hasHttp) {
                        $found[$iface] = $rc;
                        break;
                    }
                }
            }
        }

        if (empty($found)) {
            $this->output->warn("No annotated API interfaces found in {$dir}");

            return 0;
        }

        foreach ($found as $fqcn => $rc) {
            $this->output->success("  ✓  {$fqcn}");

            if ($verbose) {
                foreach ($rc->getMethods() as $method) {
                    foreach (self::HTTP_ATTRS as $attrClass) {
                        $attrs = $method->getAttributes($attrClass);
                        if (!empty($attrs)) {
                            $verb = (new \ReflectionClass($attrClass))->getShortName();
                            $path = $attrs[0]->newInstance()->path;
                            $this->output->dim("       {$verb} {$path}  → {$method->getName()}()");
                        }
                    }
                }
                $this->output->line('');
            }
        }

        $this->output->line('');
        $this->output->info(count($found) . ' annotated interface(s) found.');

        return 0;
    }

    private function findPhpFiles(string $dir): \Iterator
    {
        $rii = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($rii as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }

    private function parseArgs(array $argv): array
    {
        $result = ['_' => []];
        for ($i = 0; $i < count($argv); $i++) {
            $arg = $argv[$i];
            if (str_starts_with($arg, '--')) {
                $arg = substr($arg, 2);
                if (str_contains($arg, '=')) {
                    [$k, $v] = explode('=', $arg, 2);
                    $result[$k] = $v;
                } else {
                    $next = $argv[$i + 1] ?? null;
                    if ($next && !str_starts_with($next, '-')) {
                        $result[$arg] = $next; $i++;
                    } else {
                        $result[$arg] = true;
                    }
                }
            } elseif (str_starts_with($arg, '-') && strlen($arg) === 2) {
                $next = $argv[$i + 1] ?? null;
                if ($next && !str_starts_with($next, '-')) {
                    $result[$arg[1]] = $next; $i++;
                } else {
                    $result[$arg[1]] = true;
                }
            } else {
                $result['_'][] = $arg;
            }
        }

        return $result;
    }
}
