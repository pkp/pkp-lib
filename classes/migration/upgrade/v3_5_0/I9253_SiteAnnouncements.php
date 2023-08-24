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

use APP\core\Application;
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
        Schema::table('announcement_types', function (Blueprint $table) {
            $table->dropForeign(['context_id']);
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        $app = Application::getName();

        $contextIdColumn = 'journal_id';
        $contextTable = 'journals';
        if ($app === 'omp') {
            $contextIdColumn = 'press_id';
            $contextTable = 'presses';
        } elseif ($app === 'ops') {
            $contextIdColumn = 'server_id';
            $contextTable = 'servers';
        }

        Schema::table('announcement_types', function (Blueprint $table) use ($contextIdColumn, $contextTable) {
            $table
                ->foreign('context_id')
                ->references($contextIdColumn)
                ->on($contextTable);
        });
    }
}
