<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7191_SubmissionProgressType.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7191_SubmissionProgressType
 * @brief Change the submission_progress setting from an int to a string to match
 *   the new step ids
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class I7191_SubmissionProgressType extends \PKP\migration\Migration
{
    public function up(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->string('submission_progress_temp', 50)->default('files');
        });

        foreach ($this->getStepMap() as $oldValue => $newValue) {
            DB::table('submissions')
                ->where('submission_progress', $oldValue)
                ->update([
                    'submission_progress_temp' => $newValue,
                ]);
        }

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('submission_progress');
            $table->renameColumn('submission_progress_temp', 'submission_progress');
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->smallInteger('submission_progress_temp')->default(1);
        });

        foreach ($this->getStepMap() as $oldValue => $newValue) {
            DB::table('submissions')
                ->where('submission_progress', $newValue)
                ->update([
                    'submission_progress_temp' => $oldValue,
                ]);
        }

        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('submission_progress');
            $table->renameColumn('submission_progress_temp', 'submission_progress');
        });
    }

    /**
     * @return array [oldValue => newValue]
     */
    protected function getStepMap(): array
    {
        return [
            0 => '',
            1 => 'files',
            2 => 'contributors',
            3 => 'review',
        ];
    }
}
