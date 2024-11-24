<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10292_AddContextIdColumnToControlledVocabsTable.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10292_AddContextIdColumnToControlledVocabsTable
 *
 * @brief Add the column `context_id` to `controlled_vocabs` table for context map.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\migration\Migration;

abstract class I10292_AddContextIdColumnToControlledVocabsTable extends Migration
{
    /**
     * Name of the context table
     */
    abstract protected function getContextTable(): string;

    /**
     * Name of the context table primary key
     */
    abstract protected function getContextPrimaryKey(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('controlled_vocabs', function (Blueprint $table) {
            $table
                ->bigInteger('context_id')
                ->nullable()
                ->comment('Context to which the controlled vocab is associated');
            
            $table
                ->foreign('context_id')
                ->references($this->getContextPrimaryKey())
                ->on($this->getContextTable())
                ->onDelete('cascade');

            $table->index(['context_id'], 'controlled_vocabs_context_id');
        });

        DB::table("controlled_vocabs")
            ->select(["assoc_id"])
            ->addSelect([
                "context_id" => DB::table("submissions")
                    ->select("context_id")
                    ->whereColumn(
                        DB::raw(
                            "(SELECT publications.submission_id
                            FROM publications
                            INNER JOIN controlled_vocabs
                            ON publications.publication_id = controlled_vocabs.assoc_id
                            LIMIT 1)"
                        ),
                        "=",
                        "submissions.submission_id"
                    )
            ])
            ->whereNot("assoc_type", 0)
            ->whereNot("assoc_id", 0)
            ->get()
            ->groupBy("context_id")
            ->each (
                fn (Collection $assocs, int $contextId) => $assocs
                    ->chunk(1000)
                    ->each(
                        fn ($chunkAssocs) => DB::table('controlled_vocabs')
                            ->whereIn('assoc_id', $chunkAssocs->pluck('assoc_id')->toArray())
                            ->update(['context_id' => $contextId])
                    )
            );
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::table('controlled_vocabs', function (Blueprint $table) {
            $table->dropColumn(['context_id']);
        });
    }
}
