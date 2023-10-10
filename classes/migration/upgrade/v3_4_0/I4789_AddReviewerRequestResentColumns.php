<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I4789_AddReviewerRequestResentColumns.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4789_AddReviewerRequestResentColumns
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I4789_AddReviewerRequestResentColumns extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->smallInteger('request_resent')->default(0);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('request_resent');
        });
    }
}
