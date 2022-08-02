<?php

/**
 * @file classes/migration/install/ViewsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration\install;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ViewsMigration extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tracking of views for various types of objects such as files, reviews, etc
        Schema::create('item_views', function (Blueprint $table) {
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');

            $table->bigInteger('user_id')->nullable();
            $table->foreign('user_id')->references('user_id')->on('users');

            $table->datetime('date_last_viewed')->nullable();
            $table->unique(['assoc_type', 'assoc_id', 'user_id'], 'item_views_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::drop('item_views');
    }
}
