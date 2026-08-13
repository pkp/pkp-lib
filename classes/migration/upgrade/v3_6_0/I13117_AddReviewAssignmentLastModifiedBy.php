<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13117_AddReviewAssignmentLastModifiedBy.php
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13117_AddReviewAssignmentLastModifiedBy.php
 *
 * @brief Add last_modified_by_id column to review_assignments table.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I13117_AddReviewAssignmentLastModifiedBy extends Migration
{
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->bigInteger('last_modified_by_id')->nullable()->comment('The ID of the user who last modified the submitted review.');
            $table->foreign('last_modified_by_id')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['last_modified_by_id']);
            $table->dropColumn('last_modified_by_id');
        });
    }
}
