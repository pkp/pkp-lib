<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I11238_PrepareDBForStructuredCitations.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11238_PrepareDBForStructuredCitations
 *
 * @brief Remove setting citationsRaw from publication_settings table, make setting_type column in table citation_settings nullable.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I11238_PrepareDBForStructuredCitations extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('publication_settings')
            ->where('setting_name', 'citationsRaw')
            ->delete();

        if (Schema::hasColumn('citation_settings', 'setting_type')) {
            Schema::table('citation_settings', function (Blueprint $table) {
                $table->string('setting_type', 6)->nullable()->change();
            });
        }
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

}
