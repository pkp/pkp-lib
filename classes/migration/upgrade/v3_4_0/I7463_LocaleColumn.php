<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7463_LocaleColumn.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7463_LocaleColumn
 * @brief Remove's locale column installed to publications table
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I7463_LocaleColumn extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasColumn('publications', 'locale')) {
            Schema::table('publications', function (Blueprint $table) {
                $table->dropColumn('locale');
            });
        }
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        // Don't restore this column on downgrade
    }
}
