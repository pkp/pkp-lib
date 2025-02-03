<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I7135_CreateNewRorRegistryCacheTables.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7135_CreateNewRorRegistryCacheTables
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\task\UpdateRorRegistryDataset;

class I7135_CreateNewRorRegistryCacheTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rors', function (Blueprint $table) {
            $table->comment('Ror registry dataset cache');
            $table->bigInteger('ror_id')->autoIncrement();
            $table->string('ror')->nullable(false);
            $table->string('display_locale', 28)->default('');
            $table->smallInteger('is_active')->nullable(false)->default(1);
            $table->mediumText('search_phrase')->nullable();

            $table->unique(['ror'], 'rors_unique');
            $table->index(['display_locale'], 'rors_display_locale');
            $table->index(['is_active'], 'rors_is_active');
        });

        Schema::create('ror_settings', function (Blueprint $table) {
            $table->comment('More data about Ror registry dataset cache');
            $table->bigIncrements('ror_setting_id');
            $table->bigInteger('ror_id');
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->foreign('ror_id')
                ->references('ror_id')->on('rors')->cascadeOnDelete();
            $table->unique(['ror_id', 'locale', 'setting_name'], 'ror_settings_unique');
        });

        // update the tables with latest data set dump from Ror.org
        $updateRorRegistryDataset = new UpdateRorRegistryDataset();
        $updateRorRegistryDataset->execute();

        $this->migrateRorAffiliations();

        $this->migrateNonRorAffiliations();
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
     * Migrate affiliations with an exact name in rors table.
     * Only migrate if author has none in author_affiliations.
     *
     * select distinct a_s.author_id, r.ror
     * from author_settings as a_s
     * join ror_settings as r_s on r_s.locale = a_s.locale  and r_s.setting_value = a_s.setting_value and r_s.setting_name = 'name'
     * join rors as r on r_s.ror_id = r.ror_id
     * left join author_affiliations as a_a on a_s.author_id = a_a.author_id and r.ror = a_a.ror
     * where a_s.setting_name = 'affiliation' and a_a.author_affiliation_id is null;
     *
     */
    public function migrateRorAffiliations(): void
    {
        $sql = DB::table('author_settings as as')
            ->join(
                'ror_settings as rs',
                function (JoinClause $join) {
                    $join
                        ->on('rs.setting_value', '=', 'as.setting_value')
                        ->where('rs.setting_name', '=', 'name');
                }
            )
            ->join('rors as r', 'r.ror_id', '=', 'rs.ror_id')
            ->leftJoin(
                'author_affiliations as aa',
                function (JoinClause $join) {
                    $join
                        ->on('aa.author_id', '=', 'as.author_id')
                        ->on('aa.ror', '=', 'r.ror');
                }
            )
            ->where('aa.author_affiliation_id', '=', null)
            ->select(['as.author_id', 'r.ror'])
            ->distinct();

        DB::table('author_affiliations')->insertUsing(['author_id', 'ror'], $sql);
    }

    /**
     * Migrates affiliations which have not migrated yet.
     * Only migrate rows which don't exist.
     *
     * select distinct a.author_id, a_s.locale, a_s.setting_value
     * from authors as a
     * join author_settings as a_s on a.author_id = a_s.author_id and a_s.setting_name = 'affiliation'
     * left join author_affiliations as aa on a.author_id = aa.author_id
     * where aa.author_id is null;
     *
     */
    public function migrateNonRorAffiliations(): void
    {
        $rows = DB::table('authors as a')
            ->join(
                'author_settings as as',
                function (JoinClause $join) {
                    $join
                        ->on('a.author_id', '=', 'as.author_id')
                        ->where('as.setting_name', '=', 'affiliation');
                }
            )
            ->leftJoin('author_affiliations as aa', 'a.author_id', '=', 'aa.author_id')
            ->where('aa.author_id', '=', null)
            ->select(['a.author_id', 'as.locale', 'as.setting_value'])
            ->distinct()
            ->get();

        $rows->each(function ($row) {
            DB::table('author_affiliations')
                ->insert(['author_id' => $row->author_id]);

            $newId = DB::getPdo()->lastInsertId();
            DB::table('author_affiliation_settings')
                ->insert([
                    'author_affiliation_id' => $newId,
                    'locale' => $row->locale,
                    'setting_name' => 'name',
                    'setting_value' => $row->setting_value
                ]);
        });
    }
}
