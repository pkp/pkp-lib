<?php

/**
 * @file classes/migration/ViewsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ViewsMigration
 * @brief Describe database table structures.
 */

namespace PKP\migration;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ViewsMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        // Tracking of views for various types of objects such as files, reviews, etc
        Schema::create('item_views', function (Blueprint $table) {
            $table->bigInteger('assoc_type');
            $table->bigInteger('assoc_id');
            $table->bigInteger('user_id')->nullable();
            $table->datetime('date_last_viewed')->nullable();
            $table->unique(['assoc_type', 'assoc_id', 'user_id'], 'item_views_pkey');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
        Schema::drop('item_views');
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\migration\ViewsMigration', '\ViewsMigration');
}
