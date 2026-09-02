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
 * @brief Publish vendor package assets (e.g., log-viewer) to the public directory.
 *
 *   This migration iterates the canonical PublishablePackageRegistry and
 *   delegates copy/remove operations to PackageAssetPublisher. The package
 *   list is intentionally not stored on this class — see
 *   PKP\core\publishablePackage\PublishablePackageRegistry.
 */

namespace PKP\migration\install;

use PKP\core\publishablePackage\PackageAssetPublisher;
use PKP\core\publishablePackage\PublishablePackageRegistry;
use PKP\migration\Migration;

class PublishPackageAssetsMigration extends Migration
{
    /**
     * Publish all registered package assets to the public directory.
     */
    public function up(): void
    {
        $basePath = base_path();
        $publicPath = $basePath . '/public';

        if (!is_writable($publicPath)) {
            error_log("PublishPackageAssetsMigration: public/ directory is not writable. Run 'php lib/pkp/tools/publishPackageAssets.php publish' manually.");
            return;
        }

        foreach (PublishablePackageRegistry::all() as $package) {
            $result = PackageAssetPublisher::publish($package, $basePath, $publicPath);
            if ($result['reason'] !== null) {
                error_log("PublishPackageAssetsMigration: skipped '{$package->name}' ({$result['reason']}: {$result['source']})");
            }
        }
    }

    /**
     * Remove published assets on downgrade.
     */
    public function down(): void
    {
        $publicPath = base_path() . '/public';

        foreach (PublishablePackageRegistry::all() as $package) {
            PackageAssetPublisher::unpublish($package, $publicPath);
        }
    }
}
