<?php

/**
 * @file classes/migration/upgrade/v3_4_0/PreflightCheckMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6093_AddForeignKeys
 * @brief Check for common problems early in the upgrade process.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;

use PKP\db\DAORegistry;
use PKP\migration\Migration;

class PreflightCheckMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    }

    /**
     * Reverse the migrations.
     */
    public function down(): ?bool
    {
        if ($fallbackVersion = $this->_attributes['fallback'] ?? null) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $versionDao->insertVersion(\PKP\site\Version::fromString($fallbackVersion));
            $this->_installer->log("Upgrade failed! Fallback set to ${fallbackVersion} by pre-flight check.");
            return true;
        }
    }
}
