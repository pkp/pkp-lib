<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10359_DateConsideredToReviewAssignments.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10359_DateConsideredToReviewAssignments
 *
 * @brief Add date_considered column to the review_assignments table.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I10359_DateConsideredToReviewAssignments extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dateTime('date_considered')->after('date_completed')->nullable();
        });
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('date_considered');
        });
    }
}
