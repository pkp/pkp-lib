<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9707_WeblateUILocales.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9707_WeblateUILocales
 *
 * @brief Map old UI locales to Weblate locales
 */

namespace PKP\migration\upgrade\v3_5_0;

use DateInterval;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I9707_WeblateUILocales extends Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextIdColumn(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $affectedLocales = $this->getAffectedLocales();

        // update all locale and primary_locale columns
        $cacheKey = 'localeUpdates1 day';
        $tableLocaleColumns = Cache::remember($cacheKey, DateInterval::createFromDateString('1 day'), function () {
            $tableLocaleColumns = [];
            foreach (Schema::getTables() as $table) {
                $columns = collect(Schema::getColumns($table['name']))->whereIn('name', ['primary_locale', 'locale']);
                if ($columns->count() > 0) {
                    $tableLocaleColumns[$table['name']] = $columns;
                }
            }
            return $tableLocaleColumns;
        });

        foreach ($tableLocaleColumns as $tableName => $localeColumns) {
            $localeColumns->each(
                fn ($column) =>
                DB::table($tableName)
                    ->whereIn($column['name'], array_keys($affectedLocales))
                    ->update([$column['name'] => DB::raw(
                        "CASE {$column['name']} " . implode(' ', array_map(fn ($oldLocale, $newLocale) => "WHEN '{$oldLocale}' THEN '{$newLocale}'", array_keys($affectedLocales), array_values($affectedLocales))) . ' END'
                    )])
            );
        }

        // site supported and installed locales
        $this->updateArrayLocale('site', 'supported_locales');
        $this->updateArrayLocale('site', 'installed_locales');

        // users locales
        $this->updateArrayLocale('users', 'locales');

        // context supported locales
        $supportedDefaultSubmissionLocale = DB::table($this->getContextSettingsTable())
            ->where('setting_name', '=', 'supportedDefaultSubmissionLocale')
            ->get()
            ->pluck('setting_value')
            ->first();
        if (in_array($supportedDefaultSubmissionLocale, array_keys($affectedLocales))) {
            DB::table($this->getContextSettingsTable())
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
        $this->updateArrayLocale($this->getContextSettingsTable(), 'setting_value', $contextLocaleSettingNames);


        // plugin_settings, customBlockManager
        // assume that setting names blockTitle and blockContent are only used in customBlockManager
        $blockLocalizedSettingNames = ['blockTitle', 'blockContent'];
        $this->updateArrayLocale('plugin_settings', 'setting_value', $blockLocalizedSettingNames, true);

        Cache::forget($cacheKey);
    }

    /**
     * Update locale arrays, and locales as array keys
     */
    public function updateArrayLocale(string $tableName, string $columnName, ?array $settingNames = null, ?bool $key = false)
    {
        $affectedLocales = $this->getAffectedLocales();
        $key = $key ? ':' : '';
        $replaceString = $columnName;
        foreach ($affectedLocales as $oldLocale => $newLocale) {
            $replaceString = "REPLACE({$replaceString}, '\"{$oldLocale}\"{$key}', '\"{$newLocale}\"{$key}')";
        }
        DB::table($tableName)
            ->when(
                isset($settingNames),
                fn (Builder $query) => $query->whereIn('setting_name', $settingNames)
            )
            ->update([$columnName => DB::raw($replaceString)]);
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
