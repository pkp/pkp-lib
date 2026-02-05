<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12204_ReviewAssignmentPublicVisibility.php
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12204_ReviewAssignmentPublicVisibility.php
 *
 * @brief Add is_review_publicly_visible column to review_assignments table.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12204_ReviewAssignmentPublicVisibility extends Migration
{
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->boolean('is_review_publicly_visible')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('is_review_publicly_visible');
        });
    }

}
