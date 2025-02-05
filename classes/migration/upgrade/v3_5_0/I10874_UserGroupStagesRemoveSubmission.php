<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10874_UserGroupStagesRemoveSubmission.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10874_UserGroupStagesRemoveSubmission
 *
 * @brief Remove WORKFLOW_STAGE_ID_SUBMISSION stage from user_group_stage table
 */

namespace APP\migration\upgrade\v3_5_0;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\migration\Migration;

class I10874_UserGroupStagesRemoveSubmission extends Migration
{
    public const WORKFLOW_STAGE_SUBMISSION = 1;

    protected Collection $rowsWithSubmissionStage;

    public function up(): void
    {
        $this->rowsWithSubmissionStage = DB::table('user_group_stage')
            ->where('stage_id', self::WORKFLOW_STAGE_SUBMISSION)
            ->get();

        DB::table('user_group_stage')->where('stage_id', self::WORKFLOW_STAGE_SUBMISSION)->delete();
    }

    public function down(): void
    {
        $toInsert = [];
        foreach ($this->rowsWithSubmissionStage as $row) { /* @var \stdClass $row */
            $toInsert[] = [
                'context_id' => $row->context_id,
                'user_group_id' => $row->user_group_id,
                'stage_id' => $row->stage_id,
            ];
        }

        DB::table('user_group_stage')->insert($toInsert);
    }
}
