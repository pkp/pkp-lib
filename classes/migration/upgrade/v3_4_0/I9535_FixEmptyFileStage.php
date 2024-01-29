<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I9535_FixEmptyFileStage.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9535_FixEmptyFileStage.php
 *
 * @brief Redirect empty file stages to the SUBMISSION_FILE_SUBMISSION.
 *
 */

namespace PKP\migration\upgrade\v3_4_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I9535_FixEmptyFileStage extends Migration
{
    public function up(): void
    {
        DB::table('submission_files')
            // empty file_stage
            ->where('file_stage', 0)
            // To \PKP\submissionFile\SubmissionFile::SUBMISSION_FILE_SUBMISSION
            ->update(['file_stage' => 2]);
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
