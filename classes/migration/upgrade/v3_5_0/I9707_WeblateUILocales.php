<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9707_WeblateUILocales.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9707_WeblateUILocales
 *
 * @brief Map old UI locales to Weblate locales
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I9707_WeblateUILocales extends Migration
{
    protected string $CONTEXT_TABLE = '';
    protected string $CONTEXT_SETTINGS_TABLE = '';
    protected string $CONTEXT_COLUMN = '';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $affectedLocales = $this->getAffectedLocales();

        // update all locale and primary_locale columns
        $schemaLocName = (DB::connection() instanceof PostgresConnection) ? 'TABLE_CATALOG' : 'TABLE_SCHEMA';
        $renameLocale = fn (string $l) => collect(DB::select("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = ? AND {$schemaLocName} = ?", [$l, DB::connection()->getDatabaseName()]))
            ->each(function (\stdClass $sc) use ($l, $affectedLocales) {
                foreach ($affectedLocales as $uiLocale => $weblateLocale) {
                    DB::table($sc->TABLE_NAME ?? $sc->table_name)->where($l, '=', $uiLocale)->update([$l => $weblateLocale]);
                }
            });
        $renameLocale('primary_locale');
        $renameLocale('locale');

        // site supported and installed locales
        $site = DB::table('site')
            ->select(['supported_locales', 'installed_locales'])
            ->first();
        $this->updateArrayLocale($site->supported_locales, 'site', 'supported_locales');
        $this->updateArrayLocale($site->installed_locales, 'site', 'installed_locales');

        // users locales
        $migration = $this;
        DB::table('users')->chunkById(1000, function ($users) use ($migration) {
            foreach ($users as $user) {
                $migration->updateArrayLocale($user->locales, 'users', 'locales', null, 'user_id', $user->user_id);
            }
        }, 'user_id');

        // context supported locales
        $supportedDefaultSubmissionLocale = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedDefaultSubmissionLocale')
            ->get()
            ->pluck('setting_value')
            ->first();
        if (in_array($supportedDefaultSubmissionLocale, array_keys($affectedLocales))) {
            DB::table($this->CONTEXT_SETTINGS_TABLE)
                ->where('setting_name', '=', 'supportedDefaultSubmissionLocale')
                ->update(['setting_value' => $affectedLocales[$supportedDefaultSubmissionLocale]]);
        }
        $contextLocaleSettingNames = [
            'supportedFormLocales',
            'supportedLocales',
            'supportedSubmissionLocales',
            'supportedAddedSubmissionLocales',
            'supportedSubmissionMetadataLocales',
        ];
        foreach ($contextLocaleSettingNames as $contextLocaleSettingName) {
            $contextSettingLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
                ->where('setting_name', '=', $contextLocaleSettingName)
                ->get();
            foreach ($contextSettingLocales as $contextSettingLocale) {
                $this->updateArrayLocale($contextSettingLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'setting_value', $contextLocaleSettingName, $this->CONTEXT_COLUMN, $contextSettingLocale->{$this->CONTEXT_COLUMN});
            }
        }


        // plugin_settings
        // customBlockManager
        $blockPluginName = 'customblockmanagerplugin';
        $blockLocalizedSettingNames = ['blockTitle', 'blockContent'];

        $contextIds = DB::table($this->CONTEXT_TABLE)
            ->get()
            ->pluck($this->CONTEXT_COLUMN);

        foreach ($contextIds as $contextId) {
            $blocks = DB::table('plugin_settings')
                ->where('plugin_name', '=', $blockPluginName)
                ->where('setting_name', '=', 'blocks')
                ->where('context_id', '=', $contextId)
                ->get()
                ->pluck('setting_value');

            if (!$blocks->isEmpty()) {
                $blockNames = $blocks->first();

                $blocksArray = json_decode($blockNames, true);
                if (is_null($blocksArray)) {
                    $blocksArray = unserialize($blockNames);
                }

                foreach ($blocksArray as $block) {
                    foreach ($blockLocalizedSettingNames as $blockLocalizedSettingName) {
                        $blockLocalizedContent = DB::table('plugin_settings')
                            ->where('plugin_name', '=', $block)
                            ->where('setting_name', '=', $blockLocalizedSettingName)
                            ->where('context_id', '=', $contextId)
                            ->first();

                        if (isset($blockLocalizedContent)) {
                            $this->updateArrayKeysLocaleSetting($blockLocalizedContent->setting_value, 'plugin_settings', 'plugin_setting_id', $blockLocalizedContent->plugin_setting_id);
                        }
                    }
                }
            }
        }
    }

    /**
     * Update array of locales
     */
    public function updateArrayLocale(string $dbLocales, string $table, string $column, ?string $settingName = null, ?string $tableKeyColumn = null, ?int $id = null)
    {
        $locales = json_decode($dbLocales) ?: [];
        $affectedLocales = $this->getAffectedLocales();
        $localesToMigrate = array_intersect($locales, array_keys($affectedLocales));
        if (empty($localesToMigrate)) {
            return;
        }

        $newLocales = [];
        foreach ($locales as $locale) {
            $updatedLocale = $this->getUpdatedLocale($locale);
            if (!is_null($updatedLocale)) {
                if (!in_array($updatedLocale, $newLocales)) {
                    $newLocales[] = $updatedLocale;
                }
            } else {
                $newLocales[] = $locale;
            }
        }

        DB::table($table)
            ->when(
                isset($tableKeyColumn) && isset($id),
                fn (Builder $query) => $query->where($tableKeyColumn, '=', $id)
            )
            ->when(
                isset($settingName),
                fn (Builder $query) => $query->where('setting_name', '=', $settingName)
            )
            ->update([
                $column => $newLocales
            ]);
    }

    /**
     * Update locales that are array keys
     */
    public function updateArrayKeysLocaleSetting(string $contentArray, string $table, string $tableKeyColumn, int $id)
    {
        $contentElements = json_decode($contentArray, true) ?: [];
        $affectedLocales = $this->getAffectedLocales();
        $localesToMigrate = array_intersect_key($contentElements, $affectedLocales);
        if (empty($localesToMigrate)) {
            return;
        }

        $newLocales = [];
        foreach (array_keys($contentElements) as $locale) {
            $updatedLocale = $this->getUpdatedLocale($locale);
            if (!is_null($updatedLocale)) {
                $newLocales[$updatedLocale] = $contentElements[$locale];
            } else {
                $newLocales[$locale] = $contentElements[$locale];
            }
        }
        $jsonString = json_encode($newLocales);
        DB::table($table)
            ->where($tableKeyColumn, '=', $id)
            ->update([
                'setting_value' => $jsonString
            ]);
    }

    /**
     * Returns null if no conversion is needed or
     * the new, converted value.
     */
    public function getUpdatedLocale(string $localeValue): ?string
    {
        $affectedLocales = $this->getAffectedLocales();
        if (!in_array($localeValue, array_keys($affectedLocales))) {
            return null;
        }
        return $affectedLocales[$localeValue];
    }

    /**
     * Returns the effected locales along with the corresponding rename for each
     */
    public static function getAffectedLocales(): array
    {
        return [
            'be@cyrillic' => 'be',
            'bs' => 'bs_Latn',
            'fr_FR' => 'fr',
            'ja_JP' => 'ja',
            'nb' => 'nb_NO',
            'pt_PT' => 'pt',
            'sr@cyrillic' => 'sr_Cyrl',
            'sr@latin' => 'sr_Latn',
            'uz@cyrillic' => 'uz',
            'uz@latin' => 'uz_Latn',
            'zh_CN' => 'zh_Hans',
        ];
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
