<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11322_AddPeerReviewDois.php
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I11322_AddPeerReviewDois.php
 *
 * @brief Add the doi_id column to review_assignments and review_round_author_responses tables.
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I11332_AddPeerReviewDois extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
           $table->bigInteger('doi_id')->nullable();
           $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
           $table->index(['doi_id'], 'review_assignments_doi_id');
        });

        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->bigInteger('doi_id')->nullable();
            $table->foreign('doi_id')->references('doi_id')->on('dois')->nullOnDelete();
            $table->index(['doi_id'], 'review_round_author_responses_doi_id');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('review_assignments', function (Blueprint $table) {
            $table->dropForeign(['doi_id']);
            $table->dropIndex('review_assignments_doi_id');
            $table->dropColumn('doi_id');
        });

        Schema::table('review_round_author_responses', function (Blueprint $table) {
            $table->dropForeign(['doi_id']);
            $table->dropIndex('review_round_author_responses_doi_id');
            $table->dropColumn('doi_id');
        });
    }
}
