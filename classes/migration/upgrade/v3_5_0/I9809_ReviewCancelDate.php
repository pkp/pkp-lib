<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9809_ReviewCancelDate.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9809_ReviewCancelDate
 *
 * @brief Add review cancel date column to the review_assignments table.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9809_ReviewCancelDate extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dateTime('date_cancelled')->after('cancelled')->nullable();
        });
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('date_cancelled');
        });
    }
}
