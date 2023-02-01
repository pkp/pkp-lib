<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7486_RenameUnconsideredColumnToConsidered.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7486_RenameUnconsideredColumnToConsidered
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I7486_RenameUnconsideredColumnToConsidered extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->renameColumn('unconsidered', 'considered');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->renameColumn('considered', 'unconsidered');
        });
    }
}
