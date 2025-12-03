<?php

/**
 * @file classes/migrations/upgrade/v3_6_0/I12049_AddPublishReviewCurateSupport
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12049_AddPublishReviewCurateSupport
 *
 * @brief Add new column, publication_id, to edit_decisions table to support publish-review-curate publishing models.
 */


namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I12049_AddPublishReviewCurateSupport extends Migration
{

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->bigInteger('publication_id')->default(0);
        });

        $this->addPublicationIds();

        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->bigInteger('publication_id')->change();
            $table->foreign('publication_id', 'edit_decisions_publication_id')
                ->references('publication_id')
                ->on('publications')
                ->onDelete('cascade');
            $table->index(['publication_id'], 'edit_decisions_publication_id');
        });
    }

    /**
     * @inheritDoc
     */
    public function down(): void
    {
        Schema::table('edit_decisions', function (Blueprint $table) {
            $table->dropColumn('publication_id');
            $table->dropIndex(['edit_decisions_publication_id']);
        });
    }

    /**
     * Adds default, oldest publication version as associated publication ID for existing entries in the `edit_decisions` table.
     */
    private function addPublicationIds(): void
    {
        DB::table('edit_decisions')
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

                    DB::table('edit_decisions')
                        ->where('submission_id', '=', $submission->submission_id)
                        ->update(['publication_id' => $publicationId]);
                }
            }, 'submission_id');
    }
}
