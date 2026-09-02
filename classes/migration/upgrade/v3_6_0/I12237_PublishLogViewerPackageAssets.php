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
 * @brief Publish the log-viewer package assets when upgrading to v3.6.0.
 */

namespace PKP\migration\upgrade\v3_6_0;

use PKP\dev\ComposerScript;
use PKP\migration\Migration;

class I12237_PublishLogViewerPackageAssets extends Migration
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
