<?php

declare(strict_types=1);

$roots = [__DIR__.'/../src', __DIR__.'/../tests', __DIR__.'/../tools'];

foreach ($roots as $root) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }
        // The isolated code-style toolchain has its own vendor tree; skip it.
        if (str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'php-cs-fixer'.DIRECTORY_SEPARATOR)) {
            continue;
        }

        passthru(
            escapeshellarg(PHP_BINARY).' -n -l '.escapeshellarg($file->getPathname()),
            $exitCode,
        );
        if ($exitCode !== 0) {
            exit($exitCode);
        }
    }
}
