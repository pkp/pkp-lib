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
use PKP\facades\Repo;
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
     * Migrate affiliations with an exact name in rors table.
     * Only migrate if author has none in author_affiliations.
     *
     * select distinct r.ror_id, a_s.author_id
     * from rors as r
     * join ror_settings as r_s on r.ror_id = r_s.ror_id
     * join author_settings as a_s on r_s.setting_value = a_s.setting_value
     * where r_s.setting_name = 'name' and a_s.setting_name = 'affiliation' and r_s.locale = a_s.locale
     *
     * @return void
     */
    public function migrateRorAffiliations(): void
    {
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
            ->leftJoin('author_affiliations as aa', 'a_s.author_id', '=', 'aa.author_id')
            ->where('aa.author_id', '=', null)
            ->select(['r.ror_id', 'a_s.author_id'])
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $ror = Repo::ror()->get($row->ror_id);

            $affiliation = Repo::affiliation()->newDataObject();
            $params = [
                "id" => null,
                "authorId" => $row->author_id,
                "ror" => $ror->_data['ror'],
                "name" => $ror->_data['name']
            ];
            $affiliation->setAllData($params);

            Repo::affiliation()->dao->updateOrInsert($affiliation);
        }
    }

    /**
     * Migrates affiliations which have not migrated yet.
     * Only migrate rows which don't exist.
     *
     * select distinct a.author_id, a_s.locale, a_s.setting_value
     * from authors as a
     * join author_settings as a_s on a.author_id = a_s.author_id and a_s.setting_name = 'affiliation'
     * left join author_affiliations as aa on a.author_id = aa.author_id
     * where aa.author_id is null
     *
     * @return void
     */
    public function migrateNonRorAffiliations(): void
    {
        $rows = DB::table('authors as a')
            ->join('author_settings as a_s',
                function (JoinClause $join) {
                    $join
                        ->on('a.author_id', '=', 'a_s.author_id')
                        ->where('a_s.setting_name', '=', 'affiliation');
                }
            )
            ->leftJoin('author_affiliations as aa', 'a.author_id', '=', 'aa.author_id')
            ->where('aa.author_id', '=', null)
            ->select(['a.author_id', 'a_s.locale', 'a_s.setting_value'])
            ->distinct()
            ->get();

        foreach ($rows as $row) {
            $affiliation = Repo::affiliation()->newDataObject();
            $params = [
                "id" => null,
                "authorId" => $row->author_id,
                "ror" => '',
                "name" => [
                    $row->locale => $row->setting_value
                ]
            ];
            $affiliation->setAllData($params);

            Repo::affiliation()->dao->updateOrInsert($affiliation);
        }
    }
}
