<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12392_Funders.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12392_Funders.php
 *
 * @brief Adds migration for Funder data
 */

namespace PKP\migration\upgrade\v3_6_0;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use APP\core\Application;
use PKP\core\Core;
use PKP\migration\Migration;

class I12392_Funders extends Migration
{

    private const CONTEXT_SETTING_TABLE_NAMES = [
        'ojs2' => 'journal_settings',
        'omp' => 'press_settings',
        'ops' => 'server_settings',
    ];

    private const CONTEXT_SETTING_TABLE_KEYS = [
        'ojs2' => 'journal_id',
        'omp' => 'press_id',
        'ops' => 'server_id',
    ];
    private string $settingsTableName;
    private string $settingsTableKey;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Determine the correct context settings table and key based on application
        $applicationName = Application::get()->getName();
        $this->settingsTableName = self::CONTEXT_SETTING_TABLE_NAMES[$applicationName];
        $this->settingsTableKey = self::CONTEXT_SETTING_TABLE_KEYS[$applicationName];

        // Detect and rename Funding plugin tables by column structure
        if (Schema::hasTable('funders') && Schema::hasColumn('funders', 'funder_identification')) {
            Schema::rename('funders', 'funders_legacy');
        }
        if (Schema::hasTable('funder_settings') && Schema::hasColumn('funder_settings', 'setting_type')) {
            Schema::rename('funder_settings', 'funder_settings_legacy');
        }
        if (Schema::hasTable('funder_awards')) {
            Schema::rename('funder_awards', 'funder_awards_legacy');
        }
        if (Schema::hasTable('funder_award_settings')) {
            Schema::rename('funder_award_settings', 'funder_award_settings_legacy');
        }

        // Funders
        Schema::create('funders', function (Blueprint $table) {
            $table->comment('A funder associated with a publication.');
            $table->bigInteger('funder_id')->autoIncrement();
            $table->bigInteger('submission_id');
            $table->string('ror')->nullable();            
            $table->bigInteger('seq')->default(0);
            $table->index(['ror'], 'funders_ror');
            $table->index(['submission_id'], 'funders_submission');
            $table->foreign('submission_id', 'funders_submission')->references('submission_id')->on('submissions')->onDelete('cascade');
        });

        // Funder settings
        Schema::create('funder_settings', function (Blueprint $table) {
            $table->comment('Additional data about funders.');
            $table->bigIncrements('funder_setting_id');
            $table->bigInteger('funder_id');
            $table->string('locale', 28)->default('');
            $table->string('setting_name', 255);
            $table->mediumText('setting_value')->nullable();
            $table->foreign('funder_id', 'funder_settings_funder_id')->references('funder_id')->on('funders')->onDelete('cascade');
            $table->index(['funder_id'], 'funder_settings_funder_id');
            $table->unique(['funder_id', 'locale', 'setting_name'], 'funder_settings_unique');
        });

        // Migrate legacy funder data if it exists
        if (Schema::hasTable('funders_legacy')) {

            if (!Schema::hasTable('funder_settings_legacy')) {
                throw new \Exception(
                    'Upgrade failed: could not prepare the funder settings data for migration. ' .
                    'The funder_settings database table may be missing or has an unexpected structure. ' .
                    'Please check your database schema before running this migration.'
                );
            }

            // funder_identification was stored as a full Fundref URL e.g.
            // http://dx.doi.org/10.13039/501100001780; basename() extracts the
            // numeric ID that matches the fundref_id column in ror_fundref.csv.
            // JOIN with submissions excludes orphaned funders from the set of needed mappings.
            $neededIds = DB::table('funders_legacy')
                ->join('submissions', 'funders_legacy.submission_id', '=', 'submissions.submission_id')
                ->pluck('funders_legacy.funder_identification')
                ->map(fn($id) => basename($id))
                ->filter()
                ->unique()
                ->flip()
                ->toArray();

            $fundrefToRor = [];
            if (!empty($neededIds)) {
                $rorMap = Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/rorFundrefData/data/ror_fundref.csv';
                if (!file_exists($rorMap)) {
                    throw new \Exception(
                        'ROR-Fundref mapping file not found at ' . $rorMap . '. Please initialize the rorFundrefData submodule.'
                    );
                }
                $handle = fopen($rorMap, 'r');
                if ($handle === false) {
                    throw new \Exception('Could not open ROR-Fundref mapping file at ' . $rorMap . '.');
                }
                fgetcsv($handle); // skip header line
                while (($row = fgetcsv($handle)) !== false) {
                    [$rorId, $fundrefId] = $row;
                    if (isset($neededIds[$fundrefId])) {
                        $fundrefToRor[$fundrefId] = $rorId;
                        if (count($fundrefToRor) === count($neededIds)) {
                            break; // all needed mappings found
                        }
                    }
                }
                fclose($handle);

                $hasAwardsTable = Schema::hasTable('funder_awards_legacy');

                DB::transaction(function () use ($fundrefToRor, $hasAwardsTable) {
                    // JOIN with submissions excludes orphaned funders and provides the submission
                    // locale directly, avoiding a separate query per funder.
                    DB::table('funders_legacy')
                        ->join('submissions', 'funders_legacy.submission_id', '=', 'submissions.submission_id')
                        ->select('funders_legacy.*', 'submissions.locale')
                        ->chunkById(500, function ($chunk) use ($fundrefToRor, $hasAwardsTable) {

                            $funderIds = $chunk->pluck('funder_id');

                            $legacySettings = DB::table('funder_settings_legacy')
                                ->whereIn('funder_id', $funderIds)
                                ->get()
                                ->groupBy('funder_id');

                            $legacyAwards = $hasAwardsTable
                                ? DB::table('funder_awards_legacy')
                                    ->whereIn('funder_id', $funderIds)
                                    ->get()
                                    ->groupBy('funder_id')
                                : collect();

                            $newSettings = [];

                            foreach ($chunk as $legacyFunder) {
                                $fundrefId = basename($legacyFunder->funder_identification);
                                $ror = $fundrefToRor[$fundrefId] ?? null;

                                $funderName = null;
                                foreach ($legacySettings[$legacyFunder->funder_id] ?? [] as $setting) {
                                    if ($setting->setting_name === 'funderName') {
                                        $funderName = $setting->setting_value;
                                        break;
                                    }
                                }

                                if (!$ror && empty($funderName)) {
                                    $this->_installer->log(
                                        'WARNING: Funder (legacy ID ' . $legacyFunder->funder_id .
                                        ', submission ID ' . $legacyFunder->submission_id .
                                        ') has no ROR and no name — skipping migration of this funder.'
                                    );
                                    continue;
                                }

                                $newFunderId = DB::table('funders')->insertGetId([
                                    'submission_id' => $legacyFunder->submission_id,
                                    'ror' => $ror,
                                    'seq' => 0,
                                ]);

                                if (!$ror) {
                                    // No ROR mapping found; preserve name and original Fundref ID
                                    // so it can still be exported to Crossref etc.
                                    $newSettings[] = [
                                        'funder_id' => $newFunderId,
                                        'locale' => $legacyFunder->locale,
                                        'setting_name' => 'name',
                                        'setting_value' => $funderName,
                                    ];
                                    if ($fundrefId) {
                                        $newSettings[] = [
                                            'funder_id' => $newFunderId,
                                            'locale' => '',
                                            'setting_name' => 'fundrefId',
                                            'setting_value' => $fundrefId,
                                        ];
                                    }
                                }
                                // ROR found: name is provided at runtime from ROR data; skip migrating it

                                // Collapse awards into grants JSON
                                $awardNumbers = ($legacyAwards[$legacyFunder->funder_id] ?? collect())
                                    ->pluck('funder_award_number')
                                    ->unique()
                                    ->values();

                                if ($awardNumbers->isNotEmpty()) {
                                    $newSettings[] = [
                                        'funder_id' => $newFunderId,
                                        'locale' => '',
                                        'setting_name' => 'grants',
                                        'setting_value' => json_encode(
                                            $awardNumbers->map(fn($n) => [
                                                'grantDoi' => null,
                                                'grantNumber' => $n,
                                                'grantName' => null,
                                            ])->all()
                                        ),
                                    ];
                                }
                            }

                            foreach (array_chunk($newSettings, 500) as $settingsChunk) {
                                DB::table('funder_settings')->insert($settingsChunk);
                            }

                        }, 'funders_legacy.funder_id', 'funder_id');
                });
            }

            // Drop legacy funding plugin tables
            Schema::dropIfExists('funder_award_settings_legacy');
            Schema::dropIfExists('funder_awards_legacy');
            Schema::dropIfExists('funder_settings_legacy');
            Schema::dropIfExists('funders_legacy');

            // If funding plugin was enabled, enable new core funder support
            DB::transaction(function () {
                $enabledContextIds = DB::table('plugin_settings')
                    ->where('plugin_name', 'fundingplugin')
                    ->where('setting_name', 'enabled')
                    ->where('setting_value', '1')
                    ->where('context_id', '<>', 0)
                    ->pluck('context_id');

                if ($enabledContextIds->isNotEmpty()) {
                    DB::table($this->settingsTableName)->insert(
                        $enabledContextIds->map(fn($contextId) => [
                            $this->settingsTableKey => $contextId,
                            'locale' => '',
                            'setting_name' => 'funders',
                            'setting_value' => 'enable',
                        ])->all()
                    );

                    $grantValidationSettings = DB::table('plugin_settings')
                        ->where('plugin_name', 'fundingplugin')
                        ->where('setting_name', 'enableGrantIdValidation')
                        ->whereIn('context_id', $enabledContextIds)
                        ->select(['context_id', 'setting_value'])
                        ->get();

                    if ($grantValidationSettings->isNotEmpty()) {
                        DB::table($this->settingsTableName)->insert(
                            $grantValidationSettings->map(fn($row) => [
                                $this->settingsTableKey => $row->context_id,
                                'locale' => '',
                                'setting_name' => 'funderGrantValidation',
                                'setting_value' => $row->setting_value,
                            ])->all()
                        );
                    }
                }
            });

            // Remove old plugin settings
            DB::table('plugin_settings')
                ->where('plugin_name', '=', 'fundingplugin')
                ->delete();

            // Remove old plugin version entry
            DB::table('versions')
                ->where('product_class_name', '=', 'FundingPlugin')
                ->delete();
        }
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
