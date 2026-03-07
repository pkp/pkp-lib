<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12392_Funders.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
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
use PKP\migration\Migration;

class I12392_Funders extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {

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
            $legacyFunders = DB::table('funders_legacy')->get();

            foreach ($legacyFunders as $legacyFunder) {

                // If we want to try mapping old Funder Registry values to RORs, this is where that would happen.
                // We could use a csv file for mapping containing ROR's and matching Funder Registry DOI suffixes 

                $newFunderId = DB::table('funders')->insertGetId([
                    'submission_id' => $legacyFunder->submission_id,
                    'ror' => $legacyFunder->funder_identification,
                    'seq' => 0,
                ]);

                $settings = DB::table('funder_settings_legacy')
                    ->where('funder_id', $legacyFunder->funder_id)
                    ->get();

                foreach ($settings as $setting) {
                    DB::table('funder_settings')->insert([
                        'funder_id' => $newFunderId,
                        'locale' => $setting->locale,
                        'setting_name' => $setting->setting_name,
                        'setting_value' => $setting->setting_value,
                    ]);
                }

                $awardNumbers = DB::table('funder_awards_legacy')
                    ->where('funder_id', $legacyFunder->funder_id)
                    ->pluck('funder_award_number')
                    ->toArray();

                $awardNumbers = array_values(array_unique($awardNumbers));

                if (!empty($awardNumbers)) {
                    $grants = array_map(fn($n) => [
                        'grantNumber' => $n,
                        'grantName' => null,
                    ], $awardNumbers);

                    DB::table('funder_settings')->insert([
                        'funder_id' => $newFunderId,
                        'locale' => '',
                        'setting_name' => 'grants',
                        'setting_value' => json_encode($grants),
                    ]);
                }
            }
        }

        // DECISION: Do we want to drop the legacy tables after migration?
        Schema::dropIfExists('funder_award_settings_legacy');
        Schema::dropIfExists('funder_awards_legacy');
        Schema::dropIfExists('funder_settings_legacy');
        Schema::dropIfExists('funders_legacy');
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        throw new \PKP\install\DowngradeNotSupportedException();
    }
}
