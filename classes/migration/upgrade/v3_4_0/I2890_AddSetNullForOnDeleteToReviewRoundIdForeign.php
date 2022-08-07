<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I2890_AddSetNullForOnDeleteToReviewRoundIdForeign.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I2890_AddSetNullForOnDeleteToReviewRoundIdForeign
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I2890_AddSetNullForOnDeleteToReviewRoundIdForeign extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->dropForeign(['review_round_id']);
            $table
                ->foreign('review_round_id')
                ->references('review_round_id')
                ->on('review_rounds')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->dropForeign(['review_round_id']);
            $table
                ->foreign('review_round_id')
                ->references('review_round_id')
                ->on('review_rounds');
        });
    }
}
