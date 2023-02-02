<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6241_RequiredGenres.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6241_RequiredGenres
 * @brief Add a required column to the `genres` table (file types)
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I6241_RequiredGenres extends \PKP\migration\Migration
{
    public function up(): void
    {
        Schema::table('genres', function (Blueprint $table) {
            $table->smallInteger('required')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('genres', function (Blueprint $table) {
            $table->dropColumn('required');
        });
    }
}
