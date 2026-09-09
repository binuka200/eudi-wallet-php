<?php

declare(strict_types=1);

$roots = [__DIR__.'/../src', __DIR__.'/../tests', __DIR__.'/../tools'];

foreach ($roots as $root) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
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
