<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7486_ReviewAssignmentsReadConfirm.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7486_ReviewAssignmentsReadConfirm
 * @brief 
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I7486_ReviewAssignmentsReadConfirm extends Migration
{

    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dateTime('review_confirmed_at')->nullable();
            $table->bigInteger('review_confirming_user_id')->nullable();
            $table
                ->foreign('review_confirming_user_id')
                ->references('user_id')
                ->on('users')
                ->onDelete('set null');
        });

        DB::table('review_assignments')
            ->select(['review_id', 'submission_id', 'item_views.user_id', 'item_views.date_last_viewed'])
            ->rightJoin('item_views', function($query) {
                $query
                    ->on('item_views.assoc_id', '=', 'review_assignments.review_id')
                    ->orderBy('item_views.date_last_viewed', 'desc')
                    ->where('item_views.assoc_type', Application::ASSOC_TYPE_REVIEW_RESPONSE);
        
            })
            ->where('declined', 0)
            ->where('unconsidered', '<>', 1)
            ->cursor()
            ->each(
                fn($assignment) => DB::table('review_assignments')
                    ->where('review_id', $assignment->review_id)
                    ->update([
                        'review_confirmed_at' => $assignment->date_last_viewed,
                        'review_confirming_user_id' => $assignment->user_id,
                    ])
            );
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['review_confirming_user_id']);
            $table->dropColumn(['review_confirmed_at', 'review_confirming_user_id']);
        });
    }
}
