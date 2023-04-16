<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_SubmissionsDefaultStage.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_SubmissionsDefaultStage
 *
 * @brief Migrate the default stage id for new submissions to the production stage,
 *   which is the only stage used in OPS.
 */

namespace APP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class I7191_SubmissionsDefaultStage extends \PKP\migration\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->bigInteger('stage_id')->nullable(false)->default(WORKFLOW_STAGE_ID_PRODUCTION)->change();
        });
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->bigInteger('stage_id')->nullable(false)->default(WORKFLOW_STAGE_ID_SUBMISSION)->change();
        });
    }
}
