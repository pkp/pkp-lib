<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12078_RemoveSettingTypeInCitationSettings.php
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12078_RemoveSettingTypeInCitationSettings.php
 *
 * @brief Remove the column setting_type in the DB table citation_settings.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12078_RemoveSettingTypeInCitationSettings extends Migration
{
    /**
     * @inheritDoc
     */
    public function up(): void
    {
        if (Schema::hasColumn('citation_settings', 'setting_type')) {
            Schema::table('citation_settings', function (Blueprint $table) {
                $table->dropColumn('setting_type');
            });
        }
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
