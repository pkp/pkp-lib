<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I4860_MigratePublicationVersion.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4860_MigratePublicationVersion
 *
 * @brief Add additional columns for publication versioning and migrate existing data
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;
use PKP\publication\enums\VersionStage;

class I4860_MigratePublicationVersion extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            // Adding the enum column for VersionStage
            $table->enum('version_stage', array_column(VersionStage::cases(), 'value'))
                ->nullable();

            // Adding version_minor and version_major as integers
            $table->integer('version_minor')
                ->nullable();

            $table->integer('version_major')
                ->nullable();
        });

        // Update the version_major column based on the version column
        DB::table('publications')->update([
            'version_major' => DB::raw('version'), // Copy version to version_major
            'version_minor' => 0, // Set version_minor to 0
            'version_stage' => VersionStage::VERSION_OF_RECORD, // Set version_stage to VERSION_OF_RECORD
        ]);

        // remove the `version` column
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('version');
        });
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        // Re-add the `version` column
        Schema::table('publications', function (Blueprint $table) {
            $table->integer('version')->nullable();
        });

        // Update the `version` column based on `version_major`
        DB::table('publications')->update([
            'version' => DB::raw('version_major')
        ]);

        // Drop the added columns
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn(['version_stage', 'version_minor', 'version_major']);
        });
    }
}
