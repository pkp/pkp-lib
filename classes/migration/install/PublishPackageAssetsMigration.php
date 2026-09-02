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
 * @brief Publish vendor package assets (e.g. log-viewer) to the public directory.
 *
 */

namespace PKP\migration\install;

use PKP\dev\ComposerScript;
use PKP\migration\Migration;

class PublishPackageAssetsMigration extends Migration
{
    public function up(): void
    {
        ComposerScript::publishPackageAssets();
    }

    public function down(): void
    {
        // No-op. Published assets are regenerable build artefacts rather than data, so there is
        // nothing to roll back; leaving them in place costs nothing and avoids a recursive delete
        // under public/.
    }
}
