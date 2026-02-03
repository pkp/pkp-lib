<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I9658_UpdateReviewerAssignmentsReviewerId.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9658_UpdateReviewerAssignmentsReviewerId
 *
 * @brief Migration to update reviewer_id in review_assignments table to be nullable
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9658_UpdateReviewerAssignmentsReviewerId extends Migration
{

    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->bigInteger('reviewer_id')->nullable()->change();
            $table->foreign('reviewer_id')->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->bigInteger('reviewer_id')->nullable(false)->change();
            $table->foreign('reviewer_id')->references('user_id')->on('users');
        });
    }
}
