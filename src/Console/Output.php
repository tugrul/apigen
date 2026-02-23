<?php

declare(strict_types=1);

namespace Tugrul\ApiGen\Console;

/**
 * Minimal terminal output helper.
 * Supports ANSI colors when the output is a real TTY.
 */
final class Output
{
    private bool $colors;

    public function __construct(?bool $colors = null)
    {
        $this->colors = $colors ?? (function_exists('posix_isatty') && posix_isatty(STDOUT));
    }

    public function line(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        echo $this->colorize("\033[36m", $message) . PHP_EOL; // cyan
    }

    public function success(string $message): void
    {
        echo $this->colorize("\033[32m", $message) . PHP_EOL; // green
    }

    public function error(string $message): void
    {
        fwrite(STDERR, $this->colorize("\033[31m", $message) . PHP_EOL); // red
    }

    public function warn(string $message): void
    {
        echo $this->colorize("\033[33m", $message) . PHP_EOL; // yellow
    }

    public function dim(string $message): void
    {
        echo $this->colorize("\033[90m", $message) . PHP_EOL; // dark grey
    }

    public function header(string $message): void
    {
        $line = str_repeat('─', min(60, strlen($message) + 4));
        $this->line('');
        $this->info("┌{$line}┐");
        $this->info("│  {$message}  │");
        $this->info("└{$line}┘");
        $this->line('');
    }

    private function colorize(string $code, string $message): string
    {
        return $this->colors ? "{$code}{$message}\033[0m" : $message;
    }
}
