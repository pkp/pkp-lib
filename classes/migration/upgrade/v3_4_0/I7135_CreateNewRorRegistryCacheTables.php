<?php
/**
 * @file classes/migration/upgrade/v3_4_0/I7135_CreateNewRorRegistryCacheTables.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7135_CreateNewRorRegistryCacheTables
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_4_0;

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
            $table->smallInteger('is_active')->nullable(false)->default(0);

            $table->unique(['ror'], 'rors_unique');
            $table->index(['display_locale'], 'rors_display_locale');
            $table->index(['is_active'], 'rors_is_active');
        });

        Schema::create('ror_settings', function (Blueprint $table) {
            $table->comment('More data about Ror registry dataset cache');
            $table->bigInteger('ror_setting_id')->autoIncrement();
            $table->bigInteger('ror_id');
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();

            $table->index(['ror_id'], 'ror_settings_ror_id');
            $table->unique(['ror_id', 'locale', 'setting_name'], 'ror_settings_unique');
            $table->foreign('ror_id')
                ->references('ror_id')->on('rors')->cascadeOnDelete();
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
     * Migrates affiliations in author_settings.
     * If the name is exact as in rors and ror_settings tables,
     * ROR data is migrated.
     *
     * @return void
     */
    private function migrateRorAffiliations(): void
    {
        // migrate author.affiliation field
        // add ROR if affiliation name match
        // select r.ror_id, a_s.author_id, r.ror, r_s.setting_value, r_s.locale
        // from rors as r
        // join ror_settings as r_s on r.ror_id = r_s.ror_id
        // join `author_settings` as a_s on r_s.setting_value = a_s.setting_value
        // where r_s.setting_name = 'name' and a_s.setting_name = 'affiliation' and r_s.locale = a_s.locale
        $rows = DB::table('rors as r')
            ->join('ror_settings as r_s',
                function (JoinClause $join) {
                    $join
                        ->on('r.ror_id', '=', 'r_s.ror_id')
                        ->where('r_s.setting_name', '=', 'name');
                }
            )
            ->join('author_settings as a_s',
                function (JoinClause $join) {
                    $join
                        ->on('r_s.setting_value', '=', 'a_s.setting_value')
                        ->on('r_s.locale', '=', 'a_s.locale')
                        ->where('a_s.setting_name', '=', 'affiliation');
                }
            )
            ->select(['r.ror_id', 'a_s.author_id', 'r.ror', 'r_s.setting_value', 'r_s.locale'])
            ->get();

        foreach ($rows as $row) {
            //fixme: multiple-author-affiliations
            // insert into tables author_affiliations and author_affiliation_settings
            error_log(
                '[author_id: ' . $row['author_id'] . ']' .
                '[ror_id: ' . $row['ror_id'] . ']' .
                json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }
    }

    /**
     * Migrates affiliations which have not migrated yet.
     *
     * @return void
     */
    private function migrateNonRorAffiliations(): void
    {
        // add as custom affiliation if ROR does not match
        // select a.author_id, a_s.locale, a_s.setting_value
        // from authors as a
        // join author_settings as a_s on a.author_id = a_s.author_id and a_s.setting_name = 'affiliation'
        // left join author_affiliations as aa on a.author_id = aa.author_id
        // where aa.author_id is null
        $rows = DB::table('authors as a')
            ->join('author_settings as a_s',
                function (JoinClause $join) {
                    $join
                        ->on('a.author_id', '=', 'a_s.author_id')
                        ->where('a_s.setting_name', '=', 'affiliation');
                }
            )
            ->leftJoin('author_affiliations as aa','a.author_id', '=', 'aa.author_id')
            ->where('aa.author_id', '=', null)
            ->select(['a.author_id', 'a_s.locale', 'a_s.setting_value'])
            ->get();

        foreach ($rows as $row) {
            //fixme: multiple-author-affiliations
            // insert into tables author_affiliations and author_affiliation_settings
            error_log(
                '[author_id: ' . $row['author_id'] . ']' .
                '[locale: ' . $row['locale'] . ']' .
                '[setting_value: ' . $row['setting_value'] . ']' .
                json_encode($row, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
        }
    }
}