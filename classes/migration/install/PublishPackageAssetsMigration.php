<?php

/**
 * @file classes/migration/install/PublishPackageAssetsMigration.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublishPackageAssetsMigration
 *
 * @brief Publish Laravel package assets (e.g., log-viewer) to the public directory
 *
 * This migration copies frontend assets from vendor packages to public/vendor/
 * so they can be served as static files by the web server.
 */

namespace PKP\migration\install;

use PKP\migration\Migration;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PublishPackageAssetsMigration extends Migration
{
    /**
     * Get the registry of packages with publishable assets.
     *
     * Each package defines:
     *   - source: path relative to base directory (vendor package public assets)
     *   - destination: path relative to public/ directory
     *   - description: human-readable description of the package assets
     *
     * @return array<string, array{source: string, destination: string, description: string}>
     */
    public static function getPublishablePackages(): array
    {
        return [
            'log-viewer' => [
                'source' => 'lib/pkp/lib/vendor/opcodesio/log-viewer/public',
                'destination' => 'vendor/log-viewer',
                'description' => 'Log Viewer web interface assets',
            ],
        ];
    }

    /**
     * Publish all package assets to public directory
     */
    public function up(): void
    {
        $basePath = base_path();
        $publicPath = $basePath . '/public';

        if (!is_writable($publicPath)) {
            error_log("PublishPackageAssetsMigration: public/ directory is not writable. Run 'php lib/pkp/tools/publishPackageAssets.php publish' manually.");
            return;
        }

        foreach (static::getPublishablePackages() as $name => $config) {
            $sourcePath = $basePath . '/' . $config['source'];
            $destPath = $publicPath . '/' . $config['destination'];

            if (!is_dir($sourcePath)) {
                error_log("PublishPackageAssetsMigration: Source directory not found for package '{$name}': {$sourcePath}");
                continue;
            }

            if (!is_dir($destPath)) {
                mkdir($destPath, 0755, true);
            }

            $this->copyDirectory($sourcePath, $destPath);
        }
    }

    /**
     * Remove published assets on downgrade
     */
    public function down(): void
    {
        $publicPath = base_path() . '/public';

        foreach (static::getPublishablePackages() as $name => $config) {
            $destPath = $publicPath . '/' . $config['destination'];

            if (is_dir($destPath)) {
                $this->removeDirectory($destPath);
            }
        }
    }

    /**
     * Recursively copy a directory
     */
    protected function copyDirectory(string $source, string $destination): void
    {
        $directoryIterator = new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($directoryIterator, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $item) {
            /** @var RecursiveDirectoryIterator $innerIterator */
            $innerIterator = $iterator->getInnerIterator();
            $subPath = $innerIterator->getSubPathname();
            $destPath = $destination . DIRECTORY_SEPARATOR . $subPath;

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $parentDir = dirname($destPath);
                if (!is_dir($parentDir)) {
                    mkdir($parentDir, 0755, true);
                }

                copy($item->getPathname(), $destPath);
            }
        }
    }

    /**
     * Recursively remove a directory
     */
    protected function removeDirectory(string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($path);
    }
}
