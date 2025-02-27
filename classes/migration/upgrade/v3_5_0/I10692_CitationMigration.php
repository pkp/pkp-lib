<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I10692_MigrateCitations.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I10692_MigrateCitations
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I10692_CitationMigration extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->migrateRawCitations();

        if (Schema::hasColumn('citations', 'raw_citation')) {
            Schema::table('citations', function (Blueprint $table) {
                $table->dropColumn('raw_citation');
            });
        }

        if (Schema::hasColumn('citation_settings', 'setting_type')) {
            Schema::table('citation_settings', function (Blueprint $table) {
                $table->dropColumn('setting_type');
            });
        }
    }

    /**
     * Reverse the downgrades
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Migrates raw_citations from citations to citation_settings table.
     * This is needed because of multi-language support.
     */
    public function migrateRawCitations(): void
    {
        $values = DB::table('citations as c')
            ->select(
                'c.citation_id',
                's.locale',
                DB::raw("'raw_citation' as setting_name"),
                DB::raw("c.raw_citation as setting_value")
            )
            ->distinct()
            ->join('publications as p', 'c.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->get()
            ->map(function ($item) {
                return (array)$item;
            })
            ->all();

        DB::table('citation_settings')->upsert($values,
            ['citation_id', 'locale', 'setting_name'],
            ['setting_value']);
    }
}
