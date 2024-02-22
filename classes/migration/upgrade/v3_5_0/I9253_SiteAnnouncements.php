<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9253_SiteAnnouncements.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2000-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9253_SiteAnnouncements
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I9253_SiteAnnouncements extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->bigInteger('assoc_id')->nullable()->change();
        });
        Schema::table('announcement_types', function (Blueprint $table) {
            $table->bigInteger('context_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->bigInteger('assoc_id')->nullable(false)->change();
        });
        Schema::table('announcement_types', function (Blueprint $table) {
            $table->bigInteger('context_id')->nullable(false)->change();
        });
    }
}
