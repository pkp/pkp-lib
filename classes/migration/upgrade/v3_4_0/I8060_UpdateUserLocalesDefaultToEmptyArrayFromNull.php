<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I8060_UpdateUserLocalesDefaultToEmptyArrayFromNull.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I8060_UpdateUserLocalesDefaultToEmptyArray
 * @brief Update the users table locales column default to empty array from NULL and update existing NULL ones to []
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I8060_UpdateUserLocalesDefaultToEmptyArrayFromNull extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('locales')->update(['locales' => '[]']);

        Schema::table('users', function ($table) {
            $table->string('locales', 255)->nullable(false)->default('[]')->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function ($table) {
            $table->string('locales', 255)->nullable()->default('[]')->change();
        });
    }
}
