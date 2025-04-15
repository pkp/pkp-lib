<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I4860_MigratePublicationAddCreatedAt.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I4860_MigratePublicationAddCreatedAt
 *
 * @brief Add additional column created_at to Publication
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

class I4860_MigratePublicationAddCreatedAt extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Add created_at column
        Schema::table('publications', function (Blueprint $table) {
            $table->datetime('created_at')
                ->nullable()
                ->after('date_published');
        });

        // Default: last_modified
        DB::table('publications')->update([
            'created_at' => DB::raw('last_modified')
        ]);

        // Overwrite with submission.date_submitted for first publication per submission
        DB::table('submissions')
            ->select('submission_id', 'date_submitted')
            ->orderBy('submission_id')
            ->chunk(500, function ($submissions) {
                foreach ($submissions as $submission) {
                    $firstPublication = DB::table('publications')
                        ->where('submission_id', $submission->submission_id)
                        ->orderBy('publication_id')
                        ->first();

                    if ($firstPublication) {
                        DB::table('publications')
                            ->where('publication_id', $firstPublication->publication_id)
                            ->update(['created_at' => $submission->date_submitted]);
                    }
                }
            });

        // DB::statement("
        //     UPDATE publications p
        //     SET created_at = f.date_submitted
        //     FROM (
        //         SELECT s.submission_id, s.date_submitted, MIN(p2.publication_id) AS first_pub_id
        //         FROM submissions s
        //         INNER JOIN publications p2 ON p2.submission_id = s.submission_id
        //         GROUP BY s.submission_id, s.date_submitted
        //     ) AS f
        //     WHERE p.publication_id = f.first_pub_id
        // ");

        // Make the column NOT NULL
        Schema::table('publications', function (Blueprint $table) {
            $table->datetime('created_at')
                ->nullable(false)
                ->change();
        });
    }

    /**
     * Reverses the migration
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('date_created');
        });
    }
}
