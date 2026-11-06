<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13593_AddDateConfirmedAndDateRevertedToReviewRoundAuthorResponses.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13593_AddDateConfirmedAndDateRevertedToReviewRoundAuthorResponses
 *
 * @brief Add date_confirmed, date_reverted columns to the review_round_author_responses table.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I13593_AddDateConfirmedAndDateRevertedToReviewRoundAuthorResponses extends Migration
{
    public function up(): void
    {
        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->datetime('date_confirmed')->nullable()->comment('Date the author response was confirmed by editor.');
            $table->datetime('date_reverted')->nullable()->comment('Date the author response was reverted from confirmed to not confirmed.');
        });
    }

    public function down(): void
    {
        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->dropColumn(['date_confirmed', 'date_reverted']);
        });
    }
}
