<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I13530_RemoveDoiIdFromReviewRoundAuthorResponses.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I13530_RemoveDoiIdFromReviewRoundAuthorResponses
 *
 * @brief Remove the doi_id column from the review_round_author_responses table.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I13530_RemoveDoiIdFromReviewRoundAuthorResponses extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('review_round_author_responses', 'doi_id')) {
            return;
        }

        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->dropForeign(['doi_id']);
            $table->dropIndex('review_round_author_responses_doi_id');
            $table->dropColumn('doi_id');
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        if (Schema::hasColumn('review_round_author_responses', 'doi_id')) {
            return;
        }

        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
            $table->index(['doi_id'], 'review_round_author_responses_doi_id');
        });
    }
}
