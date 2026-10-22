<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13593_AddDateCompletedToReviewRoundAuthorResponses.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13593_AddDateCompletedToReviewRoundAuthorResponses
 *
 * @brief Add date_completed column to review_round_author_responses.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I13593_AddDateCompletedToReviewRoundAuthorResponses extends Migration
{
    public function up(): void
    {
        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->datetime('date_completed')->nullable()->comment('Date the author response was marked as completed; null if not completed.');
            $table->datetime('date_reverted')->nullable()->comment('Date the author response was last reverted from completed to not completed; null if never reverted.');
        });
    }

    public function down(): void
    {
        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->dropColumn(['date_completed', 'date_reverted']);
        });
    }
}
