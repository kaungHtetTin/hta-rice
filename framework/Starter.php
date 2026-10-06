<?php

declare(strict_types=1);

namespace Mini;

use RuntimeException;

final class Starter
{
    public static function create(string $target): void
    {
        if ($target === '' || file_exists($target)) {
            throw new RuntimeException('Provide a new directory that does not already exist.');
        }
        $parent = realpath(dirname($target));
        if ($parent === false || !is_dir($parent)) {
            throw new RuntimeException('The parent directory must exist.');
        }
        $destination = $parent . DIRECTORY_SEPARATOR . basename($target);
        $source = realpath(BASE_PATH);
        $normalized = strtolower(str_replace('\\', '/', $destination));
        $sourceNormalized = strtolower(str_replace('\\', '/', $source));
        if ($normalized === $sourceNormalized || str_starts_with($normalized, $sourceNormalized . '/')) {
            throw new RuntimeException('Create the new application outside this project directory.');
        }
        self::copyDirectory(BASE_PATH . '/starter', $destination);
        foreach (['framework', 'bootstrap', 'bin', 'starter'] as $directory) {
            self::copyDirectory(BASE_PATH . '/' . $directory, $destination . '/' . $directory);
        }
        foreach (['.htaccess', 'public/.htaccess', 'public/index.php', 'public/router.php'] as $file) {
            if (!copy(BASE_PATH . '/' . $file, $destination . '/' . $file)) {
                throw new RuntimeException('Could not copy ' . $file);
            }
        }
        copy($destination . '/.env.example', $destination . '/.env');
        echo "Created application: {$destination}\n";
        echo "Start it with: php -S localhost:8000 -t public public/router.php\n";
    }

    private static function copyDirectory(string $source, string $destination): void
    {
        if (!is_dir($destination) && !mkdir($destination, 0775, true)) {
            throw new RuntimeException('Could not create ' . $destination);
        }
        foreach (new \DirectoryIterator($source) as $entry) {
            if ($entry->isDot() || $entry->isLink()) {
                continue;
            }
            $target = $destination . '/' . $entry->getFilename();
            if ($entry->isDir()) {
                self::copyDirectory($entry->getPathname(), $target);
            } elseif (!copy($entry->getPathname(), $target)) {
                throw new RuntimeException('Could not copy ' . $entry->getFilename());
            }
        }
    }
}
