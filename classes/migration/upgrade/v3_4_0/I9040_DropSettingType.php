<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9040_DropSettingType.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9040_DropSettingType
 *
 * @brief Drop not needed setting_type fields
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I9040_DropSettingType extends Migration
{
    /**
     * Retrieve the affected entities
     * @return string[]
     */
    protected function getEntities(): array
    {
        return ['announcement_settings', 'submission_file_settings'];
    }

    /**
     * Run the migration.
     */
    public function up(): void
    {
        foreach ($this->getEntities() as $table) {
            if (Schema::hasColumn($table, 'setting_type')) {
                Schema::dropColumns($table, 'setting_type');
            }
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
