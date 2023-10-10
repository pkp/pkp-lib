<?php

/**
 * @file classes/migration/upgrade/v3_4_0/FailedJobsMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class FailedJobsMigration
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_4_0;

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
        // Schema matches https://github.com/illuminate/queue/blob/7.x/Console/stubs/failed_jobs.stub
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
        Schema::dropIfExists('failed_jobs');
    }
}
