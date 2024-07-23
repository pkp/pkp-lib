<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9895_AddAppKeyToConfigFile.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9895_AddAppKeyToConfigFile
 *
 * @brief Generate and write the app key in the config file
 */

namespace PKP\migration\upgrade\v3_5_0;

use Throwable;
use PKP\core\PKPAppKey;
use PKP\migration\Migration;

class I9895_AddAppKeyToConfigFile extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // if APP KEY already exists, nothing to do
        if (PKPAppKey::hasKey()) {
            return;
        }

        try {
            if (!PKPAppKey::hasKeyVariable()) {
                PKPAppKey::writeAppKeyVariableToConfig();
            }
            PKPAppKey::writeAppKeyToConfig(PKPAppKey::generate());
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
    }
}
