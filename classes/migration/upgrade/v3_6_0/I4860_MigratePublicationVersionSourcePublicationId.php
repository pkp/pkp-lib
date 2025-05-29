<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I4860_MigratePublicationVersionSourcePublicationId.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4860_MigratePublicationVersionSourcePublicationId
 *
 * @brief Add additional columns for publication versioning and migrate existing data
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I4860_MigratePublicationVersionSourcePublicationId extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->bigInteger('source_publication_id')->nullable();

            $table->foreign('source_publication_id', 'publications_source_publication_id')
                  ->references('publication_id')->on('publications')
                  ->nullOnDelete();

            $table->index(['source_publication_id'], 'publications_source_publication_id_index');
        });

        // Populate the source_publication_id
        DB::table('publications')
            ->select('submission_id')
            ->distinct()
            ->orderBy('submission_id')
            ->chunk(500, function ($submissions) {
                foreach ($submissions as $submission) {
                    DB::transaction(function () use ($submission) {
                        $previousId = null;

                        $publications = DB::table('publications')
                            ->select('publication_id')
                            ->where('submission_id', $submission->submission_id)
                            ->orderBy('publication_id')
                            ->get();

                        foreach ($publications as $publication) {
                            DB::table('publications')
                                ->where('publication_id', $publication->publication_id)
                                ->update(['source_publication_id' => $previousId]);

                            $previousId = $publication->publication_id;
                        }
                    });
                }
            });
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropForeign('publications_source_publication_id');
            $table->dropIndex('publications_source_publication_id_index');
            $table->dropColumn('source_publication_id');
        });
    }
}
