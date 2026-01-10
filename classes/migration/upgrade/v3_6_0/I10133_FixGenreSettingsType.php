<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I10133_FixGenreSettingsType.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10133_FixGenreSettingsType.php
 *
 * @brief Fix genre_settings.setting_type for existing installs.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I10133_FixGenreSettingsType extends Migration
{
    public function up(): void
    {
        DB::table('genre_settings')
            ->whereNull('setting_type')
            ->update(['setting_type' => 'string']);

        Schema::table('genre_settings', function (Blueprint $table) {
            $table->string('setting_type', 6)
                ->default('string')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('genre_settings', function (Blueprint $table) {
            $table->string('setting_type', 6)
                ->default(null)
                ->change();
        });
    }
}
