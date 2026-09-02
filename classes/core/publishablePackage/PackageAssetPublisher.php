<?php

/**
 * @file classes/core/publishablePackage/PackageAssetPublisher.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PackageAssetPublisher
 *
 * @brief Pure-PHP utility that publishes (or unpublishes) a PublishablePackage's
 *   assets to/from the public directory. Has no Laravel dependency, so it is
 *   safe to call from the composer post-install hook (which runs before the
 *   framework bootstraps) as well as from migrations and the CLI tool.
 *
 *   Methods do not throw — failure conditions surface via the 'reason' key on
 *   the result array so callers can iterate without try/catch.
 */

namespace PKP\core\publishablePackage;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PackageAssetPublisher
{
    /**
     * Publish a package's assets from {basePath}/{package->source}
     * to {publicPath}/{package->destination}.
     *
     * @return array{
     *     copied: string[],
     *     skipped: string[],
     *     source: string,
     *     destination: string,
     *     reason: ?string
     * }
     *   - copied / skipped: lists of sub-paths (relative to destination)
     *   - reason: null on success; otherwise one of
     *     'source_missing', 'public_not_writable', 'destination_create_failed'
     */
    public static function publish(
        PublishablePackage $package,
        string $basePath,
        string $publicPath,
        bool $force = false,
    ): array {
        $source = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $package->source;
        $destination = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . $package->destination;

        $result = [
            'copied' => [],
            'skipped' => [],
            'source' => $source,
            'destination' => $destination,
            'reason' => null,
        ];

        if (!is_dir($source)) {
            $result['reason'] = 'source_missing';
            return $result;
        }

        if (!is_dir($publicPath) || !is_writable($publicPath)) {
            $result['reason'] = 'public_not_writable';
            return $result;
        }

        if (!is_dir($destination)
            && !mkdir($destination, 0755, true)
            && !is_dir($destination)
        ) {
            $result['reason'] = 'destination_create_failed';
            return $result;
        }

        return self::copyDirectory($source, $destination, $force, $result);
    }

    /**
     * Remove a package's published assets at {publicPath}/{package->destination}.
     * No-op if the destination doesn't exist.
     */
    public static function unpublish(PublishablePackage $package, string $publicPath): void
    {
        $destination = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . $package->destination;
        if (is_dir($destination)) {
            self::removeDirectory($destination);
        }
    }

    /**
     * Determine whether a package's assets are already published — i.e. the
     * destination directory exists and contains at least one file.
     */
    public static function isPublished(PublishablePackage $package, string $publicPath): bool
    {
        $destination = rtrim($publicPath, '/\\') . DIRECTORY_SEPARATOR . $package->destination;

        if (!is_dir($destination)) {
            return false;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($destination, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $item) {
            if ($item->isFile()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Recursively copy $source into $destination, accumulating per-file outcomes.
     *
     * @param array{copied: string[], skipped: string[], source: string, destination: string, reason: ?string} $result
     *
     * @return array{copied: string[], skipped: string[], source: string, destination: string, reason: ?string}
     */
    private static function copyDirectory(
        string $source,
        string $destination,
        bool $force,
        array $result,
    ): array {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            /** @var RecursiveDirectoryIterator $inner */
            $inner = $iterator->getInnerIterator();
            $subPath = $inner->getSubPathname();
            $target = $destination . DIRECTORY_SEPARATOR . $subPath;

            if ($item->isDir()) {
                if (!is_dir($target)) {
                    @mkdir($target, 0755, true);
                }
                continue;
            }

            if (!$force && file_exists($target)) {
                $result['skipped'][] = $subPath;
                continue;
            }

            $parentDir = dirname($target);
            if (!is_dir($parentDir)) {
                @mkdir($parentDir, 0755, true);
            }

            if (@copy($item->getPathname(), $target)) {
                $result['copied'][] = $subPath;
            }
        }

        return $result;
    }

    /**
     * Recursively remove $directory and all its contents.
     */
    private static function removeDirectory(string $directory): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
