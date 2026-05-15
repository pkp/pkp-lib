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
        
            DB::transaction(function () {
    
                $legacyFunders = DB::table('funders_legacy')->orderBy('funder_id')->get();
                
                $legacySettings = DB::table('funder_settings_legacy')
                    ->get()
                    ->groupBy('funder_id');
                            
                $legacyAwards = Schema::hasTable('funder_awards_legacy')
                    ? DB::table('funder_awards_legacy')->get()->groupBy('funder_id')
                    : collect();

                $submissionLocales = DB::table('submissions')
                        ->whereIn('submission_id', $legacyFunders->pluck('submission_id'))
                        ->pluck('locale', 'submission_id');

                $newSettings = [];

                $rorMap = Core::getBaseDir() . '/' . PKP_LIB_PATH . '/lib/rorFundrefData/data/ror_fundref.csv';
                $fundrefToRor = [];

                if (!file_exists($rorMap)) {
                    $this->_installer->log('WARNING: Funder Registry to ROR mapping file not found at ' . $rorMap . '. Funders will be migrated without ROR mappings.');
                } else {
                    $handle = fopen($rorMap, 'r');
                    fgetcsv($handle); // skip first line
                    while (($row = fgetcsv($handle)) !== false) {
                        [$rorId, $fundrefId] = $row;
                        $fundrefToRor[$fundrefId] = $rorId;
                    }
                    fclose($handle);
                }

                foreach ($legacyFunders as $legacyFunder) {

                    // map legacy funder_identification to ROR where possible
                    $fundrefId = basename($legacyFunder->funder_identification);
                    $ror = $fundrefToRor[$fundrefId] ?? null;

                    $newFunderId = DB::table('funders')->insertGetId([
                            'submission_id' => $legacyFunder->submission_id,
                            'ror' => $ror,
                            'seq' => 0,
                        ]);

                    $locale = $submissionLocales[$legacyFunder->submission_id];

                    // Migrate settings, remapping funderName -> name
                    foreach ($legacySettings[$legacyFunder->funder_id] ?? [] as $setting) {
                        if ($setting->setting_name === 'funderName') {
                            // Only migrate name if we have no ROR (ROR provides name at runtime)
                            if (!$ror) {
                                $newSettings[] = [ 
                                    'funder_id' => $newFunderId,
                                    'locale' => $locale,
                                    'setting_name' => 'name',
                                    'setting_value' => $setting->setting_value,
                                ];
                            }
                        }
                    }

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

                // Batch insert all settings at once
                foreach (array_chunk($newSettings, 100) as $chunk) {
                    DB::table('funder_settings')->insert($chunk);
                }

            });

            // Drop legacy funding plugin tables
            Schema::dropIfExists('funder_award_settings_legacy');
            Schema::dropIfExists('funder_awards_legacy');
            Schema::dropIfExists('funder_settings_legacy');
            Schema::dropIfExists('funders_legacy');

            // If funding plugin was enabled, enable new core funder support
            $results = DB::table('plugin_settings')
                ->where('plugin_name', '=', 'fundingplugin')
                ->where('context_id', '<>', 0)
                ->select(['context_id', 'setting_name', 'setting_value'])
                ->get();

            $enabledContexts = $results
                ->where('setting_name', 'enabled')
                ->where('setting_value', '1')
                ->pluck('context_id')
                ->toArray();

            $mappedResults = $results->map(function ($item) {

                // Set locale to empty string as these settings are not localized
                $item->locale = '';

                // Map old enabled setting to new funders setting
                if ($item->setting_name === 'enabled' && $item->setting_value === '1') {
                    $item->setting_name = 'funders';
                    $item->setting_value = 'enable';
                }
                
                // Map old grant validation setting to new setting if funding plugin was enabled
                if ($item->setting_name === 'enableGrantIdValidation' && in_array($item->context_id, $enabledContexts)) {
                    $item->setting_name = 'funderGrantValidation';
                }

                $item->{$this->settingsTableKey} = $item->context_id;
                unset($item->context_id);

                return (array)$item;
            })
            ->filter(function ($item) {
                return in_array($item['setting_name'], [
                    'funders',
                    'funderGrantValidation',
                ]);
            });

            foreach ($mappedResults->chunk(16000) as $mappedResultsChunk) {
                DB::table($this->settingsTableName)
                    ->insert($mappedResultsChunk->toArray());
            }            

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
