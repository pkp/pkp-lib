<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I11673_AddMissingApprovalToReviewerSuggestion.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11673_AddMissingApprovalToReviewerSuggestion
 *
 * @brief Adds missing approval to reviewer suggestion
 * @see https://github.com/pkp/pkp-lib/issues/11673
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I11673_AddMissingApprovalToReviewerSuggestion extends Migration
{
    public function up(): void
    {
        // Get all those reviewer suggestion for which there is not approver and no reviewer yet ,
        // but there is a same email address matching user and there is a review assignment for 
        // that user and the submission as the reviewer suggestion .
        $suggestions = DB::table('reviewer_suggestions')
            ->join('users', 'reviewer_suggestions.email', '=', 'users.email')
            ->join('review_assignments', function ($join) {
                $join
                    ->on(
                        'users.user_id',
                        '=',
                        'review_assignments.reviewer_id'
                    )
                    ->on(
                        'reviewer_suggestions.submission_id',
                        '=',
                        'review_assignments.submission_id'
                    );
            })
            ->whereNull('reviewer_suggestions.approved_at')
            ->whereNull('reviewer_suggestions.reviewer_id')
            ->select('reviewer_suggestions.*', 'users.user_id', 'review_assignments.review_id')
            ->get();

        foreach ($suggestions as $suggestion) {
            // As we don't know when it was approved, we will set to current time
            // But it's not possible to add approver id though it's not a required data
            // to make the suggestion approved
            DB::table('reviewer_suggestions')
                ->where('reviewer_suggestion_id', $suggestion->reviewer_suggestion_id)
                ->update([
                    'approved_at' => now(),
                    'reviewer_id' => $suggestion->user_id,
                ]);
        }
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
