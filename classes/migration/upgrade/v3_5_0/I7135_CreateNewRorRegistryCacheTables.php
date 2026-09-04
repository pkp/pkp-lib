<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I7135_CreateNewRorRegistryCacheTables.php
 *
 * Copyright (c) 2025-2026 Simon Fraser University
 * Copyright (c) 2025-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7135_CreateNewRorRegistryCacheTables
 *
 * @brief Describe database table structures.
 */

namespace PKP\migration\upgrade\v3_5_0;

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
        $rorImportSucceeded = $updateRorRegistryDataset->execute();

        $this->migrateAffiliations($rorImportSucceeded);
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
     * Migrate the legacy per-author affiliation into one author_affiliations row.
     *
     * Every localized author_settings 'affiliation' string is kept verbatim as a
     * 'name'. 'ror' is taken from a syntactically valid ROR plugin 'rorId' setting
     * and then reconciled with the just-updated rors cache:
     *  - resolves in the cache: store 'ror', drop the 'name' (3.5's ROR xor name)
     *  - not in the cache, import succeeded: drop the 'ror', keep the name
     *  - not in the cache, import failed: cannot judge, keep both 'ror' and name
     *
     * Skips authors that already have an entry in author_affiliations.
     */
    public function migrateAffiliations(bool $rorCacheComplete): void
    {
        $rows = DB::table('authors as a')
            ->join('author_settings as aus', 'a.author_id', '=', 'aus.author_id')
            ->leftJoin('author_affiliations as aa', 'aa.author_id', '=', 'a.author_id')
            ->whereNull('aa.author_id')
            ->whereIn('aus.setting_name', ['affiliation', 'rorId'])
            ->whereNotNull('aus.setting_value')
            ->where('aus.setting_value', '<>', '')
            ->select(['a.author_id', 'aus.locale', 'aus.setting_name', 'aus.setting_value'])
            ->distinct()
            ->get()
            ->groupBy('author_id');

        // which of the referenced rorIds are present in the just-updated cache?
        $referencedRors = $rows->collapse()
            ->where('setting_name', 'rorId')
            ->map(fn ($row) => $this->normalizeRor($row->setting_value))
            ->filter()
            ->unique();
        $knownRors = [];
        foreach (array_chunk($referencedRors->all(), 5000) as $rorChunk) {
            $knownRors += DB::table('rors')->whereIn('ror', $rorChunk)->pluck('ror', 'ror')->all();
        }

        // build the insert rows up front so the transaction only has to do the id wiring
        $affiliationRows = [];
        $pendingNames = [];
        foreach ($rows as $authorId => $authorRows) {
            $ror = $this->normalizeRor($authorRows->firstWhere('setting_name', 'rorId')?->setting_value);
            $rorIsKnown = $ror !== null && isset($knownRors[$ror]);

            // a normalized rorId missing from a complete import is probably a typo from the old
            // free-text plugin (ROR never removes ids) - drop it, keep the name
            if ($ror !== null && !$rorIsKnown && $rorCacheComplete) {
                $ror = null;
            }

            $names = $rorIsKnown
                ? collect()
                : $authorRows
                    ->where('setting_name', 'affiliation')
                    ->filter(fn ($row) => trim((string) $row->setting_value) !== '');

            if ($ror === null && $names->isEmpty()) {
                continue;
            }

            $affiliationRows[] = ['author_id' => $authorId, 'ror' => $ror];
            foreach ($names as $nameRow) {
                $pendingNames[] = [$authorId, $nameRow->locale, $nameRow->setting_value];
            }
        }

        if (empty($affiliationRows)) {
            return;
        }

        $authorIds = array_column($affiliationRows, 'author_id');

        DB::transaction(function () use ($affiliationRows, $authorIds, $pendingNames) {
            // one author_affiliations row per author
            foreach (array_chunk($affiliationRows, 1000) as $chunk) {
                DB::table('author_affiliations')->insert($chunk);
            }

            // each of these authors had no affiliation before, so exactly one row maps back
            $affiliationIds = [];
            foreach (array_chunk($authorIds, 5000) as $authorIdChunk) {
                $affiliationIds += DB::table('author_affiliations')
                    ->whereIn('author_id', $authorIdChunk)
                    ->pluck('author_affiliation_id', 'author_id')
                    ->all();
            }

            // wire the new ids into the name settings and insert
            foreach (array_chunk($pendingNames, 1000) as $chunk) {
                DB::table('author_affiliation_settings')->insert(array_map(fn ($n) => [
                    'author_affiliation_id' => $affiliationIds[$n[0]],
                    'locale' => $n[1],
                    'setting_name' => 'name',
                    'setting_value' => $n[2],
                ], $chunk));
            }
        });
    }

    /**
     * Normalize a value from the legacy ROR plugin 'rorId' setting to the
     * canonical https://ror.org/<id> form, or null when it is not a syntactically
     * valid ROR id (existence in the rors table is not checked).
     * The pattern mirrors the one used by PKPRorController.
     */
    private function normalizeRor(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // just in case: strip surrounding whitespace or a trailing slash so an otherwise-valid id still passes the check below
        $value = rtrim(trim($value), '/');
        $value = preg_replace('#^http://#i', 'https://', $value);

        // accept a bare id as well as the full URL both plugin versions stored
        if (preg_match('#^0[^ILOU]{6}\d{2}$#', $value)) {
            $value = 'https://ror.org/' . $value;
        }

        return preg_match('#^https://ror\.org/0[^ILOU]{6}\d{2}$#', $value) ? $value : null;
    }
}
