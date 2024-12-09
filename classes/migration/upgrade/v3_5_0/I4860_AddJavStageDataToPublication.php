<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I4860_AddJavStageDataToPublication.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4860_AddJavStageDataToPublication
 *
 * @brief Add columns for JAV Versioning and migrate existing data
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use PKP\publication\enums\JavStage;
use PKP\publication\helpers\JavStageAndNumbering;

class I4860_AddJavStageDataToPublication extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            // Adding the enum column for VersionStage
            $table->enum('jav_version_stage', array_column(JavStage::cases(), 'value'))
                ->default(JavStage::VERSION_OF_RECORD);

            // Adding minorVersion and majorVersion as integers
            $table->integer('jav_version_minor')
                ->default(JavStageAndNumbering::JAV_DEFAULT_NUMBERING_MINOR);

            $table->integer('jav_version_major')
                ->default(JavStageAndNumbering::JAV_DEFAULT_NUMBERING_MAJOR);
        });

        // Update the version_major column based on the version column
        DB::table('publications')->update([
            'jav_version_major' => DB::raw('version')
        ]);
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn(['jav_version_stage', 'jav_version_minor', 'jav_version_major']);
        });
    }
}
