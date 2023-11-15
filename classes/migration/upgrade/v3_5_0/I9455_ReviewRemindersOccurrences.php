<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9455_ReviewRemindersOccurrences.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9455_ReviewRemindersOccurrences
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9455_ReviewRemindersOccurrences extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->after('date_rated', function (Blueprint $table) {
                $table->dateTime('date_invite_reminded')->nullable();
                $table->smallInteger('count_invite_reminder')->default(0);
            });

            // Rename date_reminded to date_submit_reminded to avoid confusion with another column with a similar name
            $table->renameColumn('date_reminded', 'date_submit_reminded');

            $table->after('date_reminded', function (Blueprint $table) {
                $table->smallInteger('count_submit_reminder')->default(0);
            });
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropColumn('date_invite_reminded');
            $table->dropColumn('count_invite_reminder');
            $table->renameColumn('date_submit_reminded', 'date_reminded');
            $table->dropColumn('count_submit_reminder');
        });
    }
}
