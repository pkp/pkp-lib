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
use PKP\install\DowngradeNotSupportedException;
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

        // will set an error if app key variable not set
        // but will not halt the process
        if (!PKPAppKey::hasKeyVariable()) {
            error_log("No key variable named `app_key` defined in the `general` section of config file. Please update the config file's general section and add line `app_key = `");
            return;
        }

        try {
            PKPAppKey::writeToConfig(PKPAppKey::generate());
        } catch (Throwable $exception) {
            error_log($exception->getMessage());
        }
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
