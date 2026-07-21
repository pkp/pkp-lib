<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12994_CompetingInterestsDeclared.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12994_CompetingInterestsDeclared
 *
 * @brief Add competing_interests_declared column to review_assignments, recording
 *   whether the reviewer answered the competing interests question.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12994_CompetingInterestsDeclared extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('review_assignments', 'competing_interests_declared')) {
            Schema::table('review_assignments', function (Blueprint $table) {
                $table->boolean('competing_interests_declared')->default(false)->comment('Whether the reviewer answered the competing interests question; false means no declaration is on record.');
            });
        }

        // A stored statement proves a declaration was made. Rows where the reviewer
        // declared no competing interests were stored as NULL, indistinguishable from
        // never having been asked, so they conservatively keep the default false.
        DB::table('review_assignments')
            ->whereNotNull('competing_interests')
            ->where('competing_interests', '!=', '')
            ->update(['competing_interests_declared' => true]);
    }

    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('competing_interests_declared');
        });
    }
}
