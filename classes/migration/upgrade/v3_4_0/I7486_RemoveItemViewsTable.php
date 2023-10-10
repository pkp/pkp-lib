<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7486_RemoveItemViewsTable.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7486_RemoveItemViewsTable
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I7486_RemoveItemViewsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::drop('item_views');
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::create('item_views', function (Blueprint $table) {
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users')->onDelete('cascade');
            $table->index(['user_id'], 'item_views_user_id');

            $table->datetime('date_last_viewed')->nullable();
            $table->unique(['assoc_type', 'assoc_id', 'user_id'], 'item_views_pkey');
        });
    }
}
