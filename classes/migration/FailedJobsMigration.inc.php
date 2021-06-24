<?php

/**
 * @file classes/migration/FailedJobsMigration.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FailedJobsMigration
 * @brief Describe database table structures.
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class FailedJobsMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down()
    {
        Schema::drop('failed_jobs');
    }
}
