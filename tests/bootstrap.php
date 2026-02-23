<?php

declare(strict_types=1);

$autoloader = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($autoloader)) {
    fwrite(STDERR, "Error: vendor/autoload.php not found.\n");
    fwrite(STDERR, "Run: composer install\n");
    exit(1);
}

require_once $autoloader;
