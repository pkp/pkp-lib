<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12237_CreateLogsDirectory.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12237_CreateLogsDirectory
 *
 * @brief Create logs directory under files_dir for Laravel logging
 */

namespace PKP\migration\upgrade\v3_6_0;

use PKP\config\Config;
use PKP\file\FileManager;
use PKP\migration\Migration;

class I12237_CreateLogsDirectory extends Migration
{
    public function up(): void
    {
        $filesDir = Config::getVar('files', 'files_dir');
        $logsDir = $filesDir . '/logs';

        if (!file_exists($logsDir)) {
            $fileManager = new FileManager();
            $fileManager->mkdir($logsDir);
        }
    }

    public function down(): void
    {
        // Don't delete logs directory on downgrade — data loss risk
    }
}
