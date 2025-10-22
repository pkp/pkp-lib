<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I11913_AssociatePublicationsWithReviewRounds.php
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.

 * @class I11913_AssociatePublicationsWithReviewRounds.php
 * @brief Add publication ID to review rounds table
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I11913_AssociatePublicationsWithReviewRounds extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('review_rounds', function (Blueprint $table) {
            $table->bigInteger('publication_id');
        });

        $this->addPublicationIds();

        Schema::table('review_rounds', function (Blueprint $table) {
            $table->foreign('publication_id')
                ->references('publication_id')
                ->on('publications');
            $table->index(['publication_id'], 'review_rounds_publication_id');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('review_rounds', function (Blueprint $table) {
            $table->dropColumn('publication_id');
            $table->dropIndex(['review_rounds_publication_id']);
        });
    }

    /**
     * Adds first publication ID for a submission to reviews.
     * This assumes reviews are completed for initial submissions, pre-publication.
     */
    private function addPublicationIds(): void
    {
        DB::table('review_rounds')
            ->select(['submission_id'])
            ->distinct()
            ->chunkById(100, function (Collection $submissions) {
                foreach ($submissions as $submission) {
                    // We should include by default the first publication,
                    // so we should get the oldest existing publication for each submission
                    $publicationId = DB::table('publications')
                        ->where('submission_id', '=', $submission->submission_id)
                        ->orderBy('created_at')
                        ->pluck('publication_id')
                        ->first();

                    DB::table('review_rounds')
                        ->where('submission_id', '=', $submission->submission_id)
                        ->update(['publication_id' => $publicationId]);
                }
            }, 'submission_id');
    }
}
