<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I4904_UsageStatsTemporaryRecords.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4904_UsageStatsTemporaryRecords
 * @brief Describe upgrade/downgrade operations for DB table usage_stats_temporary_records.
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I4904_UsageStatsTemporaryRecords extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // pkp/pkp-lib#4904: additional column in the table usage_stats_temporary_records
        if (Schema::hasTable('usage_stats_temporary_records') && !Schema::hasColumn('usage_stats_temporary_records', 'representation_id')) {
            Schema::table('usage_stats_temporary_records', function (Blueprint $table) {
                $table->bigInteger('representation_id')->nullable()->default(null);
            });
        }
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
    }
}
