<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Console;

/**
 * CLI application router.
 *
 * Commands:
 *   generate   Generate stub classes from annotated interfaces
 *   list       List annotated interfaces in a directory
 *   help       Show help information
 *   version    Show version
 */
final class Application
{
    private const VERSION = '1.0.0';

    public function __construct(private readonly Output $output = new Output()) {}

    public function run(array $argv): int
    {
        // Strip the script name
        array_shift($argv);

        $command = $argv[0] ?? 'help';

        // Strip the command itself from remaining args
        if (!str_starts_with($command, '-')) {
            array_shift($argv);
        } else {
            $command = 'help';
        }

        return match ($command) {
            'generate', 'gen', 'g' => (new GenerateCommand($this->output))->run($argv),
            'list', 'ls'           => (new ListCommand($this->output))->run($argv),
            'version', '--version' => $this->showVersion(),
            'help', '--help', '-h' => $this->showHelp(),
            default                => $this->unknownCommand($command),
        };
    }

    private function showVersion(): int
    {
        $this->output->line('Tugrul ApiGen v' . self::VERSION);

        return 0;
    }

    private function showHelp(): int
    {
        $v = self::VERSION;
        $this->output->line(<<<HELP

        ┌──────────────────────────────────────────────────────────┐
        │  Tugrul ApiGen v{$v} — REST SDK Generator               │
        └──────────────────────────────────────────────────────────┘

        Usage:
          apigen <command> [arguments] [options]

        Commands:
          generate   Generate stub/impl classes from annotated interfaces
          list       Scan and list annotated API interfaces in a directory
          version    Print version
          help       Show this help

        Examples:
          apigen generate "MyApp\Api\UserApi" --output=src/Generated
          apigen generate "MyApp\Api\UserApi" --naming=suffix:Stub
          apigen generate "MyApp\Api\UserApi" --naming=subns:Generated
          apigen generate --config=apigen.php
          apigen generate --config=apigen.php --dry-run
          apigen list --dir=src/Api --verbose

        Naming strategies:
          default              Same class name as interface; "Impl" suffix on conflict
          suffix:<S>           Append suffix S   (e.g. suffix:Stub → UserApiStub)
          subns:<NS>           Child namespace   (e.g. subns:Generated → MyApp\Api\Generated\UserApi)
          subns+suffix:<NS>:<S> Both             (e.g. subns+suffix:Generated:Stub)

        Config file (apigen.php):
          return [
              'bootstrap'  => __DIR__ . '/vendor/autoload.php',
              'interfaces' => [UserApi::class, ProductApi::class],
              'output_dir' => __DIR__ . '/generated',
              'naming'     => new SubNamespaceNamingStrategy('Generated'),
          ];

        Run 'apigen generate --help' for full generate options.

        HELP);

        return 0;
    }

    private function unknownCommand(string $command): int
    {
        $this->output->error("Unknown command: {$command}");
        $this->output->line("Run 'apigen help' for usage.");

        return 1;
    }
}
