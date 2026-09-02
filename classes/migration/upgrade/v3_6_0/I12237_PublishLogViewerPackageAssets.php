<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12237_PublishLogViewerPackageAssets.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12237_PublishLogViewerPackageAssets
 *
 * @brief Publish log-viewer package assets during upgrade to v3.6.0.
 *
 *   This migration looks up the log-viewer package from PublishablePackageRegistry
 *   and delegates publish/unpublish to PackageAssetPublisher. If the package is
 *   ever removed from the registry in a future release, this migration becomes
 *   a no-op — which is correct, since there is nothing to publish.
 *
 *   Note: this is a deliberate relaxation of the strict frozen-snapshot pattern
 *   typically used for upgrade migrations. For asset publishing the practical
 *   risk is low (file copy only, no data mutation), so we accept the small
 *   reduction in historical reproducibility in exchange for not duplicating
 *   the package definition.
 */

namespace PKP\migration\upgrade\v3_6_0;

use PKP\core\publishablePackage\PackageAssetPublisher;
use PKP\core\publishablePackage\PublishablePackageRegistry;
use PKP\migration\Migration;

class I12237_PublishLogViewerPackageAssets extends Migration
{
    private const PACKAGE_NAME = 'log-viewer';

    public function up(): void
    {
        $package = PublishablePackageRegistry::get(self::PACKAGE_NAME);
        if ($package === null) {
            return;
        }

        $basePath = base_path();
        $publicPath = $basePath . '/public';

        if (!is_writable($publicPath)) {
            error_log("I12237_PublishLogViewerPackageAssets: public/ directory is not writable. Run 'php lib/pkp/tools/publishPackageAssets.php publish' manually.");
            return;
        }

        $result = PackageAssetPublisher::publish($package, $basePath, $publicPath);
        if ($result['reason'] !== null) {
            error_log("I12237_PublishLogViewerPackageAssets: skipped log-viewer ({$result['reason']}: {$result['source']})");
        }
    }

    public function down(): void
    {
        $package = PublishablePackageRegistry::get(self::PACKAGE_NAME);
        if ($package === null) {
            return;
        }

        PackageAssetPublisher::unpublish($package, base_path() . '/public');
    }
}
