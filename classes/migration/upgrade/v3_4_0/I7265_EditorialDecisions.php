<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7265_EditorialDecisions.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7265_EditorialDecisions
 * @brief Database migrations for editorial decision refactor.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

abstract class I7265_EditorialDecisions extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->upReviewRounds();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->downReviewRounds();
    }

    /**
     * Use null instead of 0 for editorial decisions not in review rounds
     */
    protected function upReviewRounds()
    {
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->bigInteger('review_round_id')->nullable()->change();
            $table->bigInteger('round')->nullable()->change();
        });

        DB::table('edit_decisions')
            ->where('review_round_id', '=', 0)
            ->orWhere('round', '=', 0)
            ->update([
                'review_round_id' => null,
                'round' => null
            ]);

        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->foreign('review_round_id')->references('review_round_id')->on('review_rounds');
            $table->index(['review_round_id'], 'edit_decisions_review_round_id');
        });
    }

    /**
     * Restore 0 values instead of null for editorial decisions not in review rounds
     */
    protected function downReviewRounds()
    {
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->dropForeign(['review_round_id']);
        });

        DB::table('edit_decisions')
            ->whereNull('review_round_id')
            ->orWhereNull('round')
            ->update([
                'review_round_id' => 0,
                'round' => 0
            ]);

        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->bigInteger('review_round_id')->nullable(false)->change();
            $table->bigInteger('round')->nullable(false)->change();
        });
    }
}
