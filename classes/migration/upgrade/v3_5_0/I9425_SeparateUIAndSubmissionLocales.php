<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9425_SeparateUIAndSubmissionLocales.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9425_SeparateUIAndSubmissionLocales
 *
 * @brief pkp/pkp-lib#9425 Make submission language selection and metadata forms independent from website language settings
 */

namespace PKP\migration\upgrade\v3_5_0;

use DateInterval;
use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

abstract class I9425_SeparateUIAndSubmissionLocales extends Migration
{
    abstract protected function getContextTable(): string;
    abstract protected function getContextSettingsTable(): string;
    abstract protected function getContextIdColumn(): string;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Update/add locale arrays
         */
        $isPostgres = DB::connection() instanceof PostgresConnection;

        $insert = function (object $localeId, string $settingName, string $settingValue): void {
            DB::table($this->getContextSettingsTable())->insert(
                [
                    $this->getContextIdColumn() => $localeId->{$this->getContextIdColumn()},
                    'locale' => '',
                    'setting_name' => $settingName,
                    'setting_value' => $settingValue,
                ]
            );
        };

        $update = function (object $localeId, string $settingName, string $settingValue): void {
            DB::table($this->getContextSettingsTable())
                ->where($this->getContextIdColumn(), '=', $localeId->{$this->getContextIdColumn()})
                ->where('setting_name', '=', $settingName)
                ->update(['setting_value' => $settingValue]);
        };

        $pluck = fn (object $localeId, string $settingName): array => json_decode(
            DB::table($this->getContextSettingsTable())
                ->where($this->getContextIdColumn(), '=', $localeId->{$this->getContextIdColumn()})
                ->where('setting_name', '=', $settingName)
                ->pluck('setting_value')[0]
        );

        $union = fn (array $a, array $b): string => collect($a)
            ->concat($b)
            ->unique()
            ->sort()
            ->values()
            ->toJson();

        foreach (DB::table($this->getContextTable())->select('primary_locale', $this->getContextIdColumn())->get() as $localeId) {
            // Add primary locale to form locales
            $formLocales = $union([$localeId->primary_locale], $pluck($localeId, 'supportedFormLocales'));
            $update($localeId, 'supportedFormLocales', $formLocales);
            // Add primary locale to submission locales
            $submissionLocales = $union([$localeId->primary_locale], $pluck($localeId, 'supportedSubmissionLocales'));
            $update($localeId, 'supportedSubmissionLocales', $submissionLocales);
            // supportedDefaultSubmissionLocale from primary locale
            $insert($localeId, 'supportedDefaultSubmissionLocale', $localeId->primary_locale);
            // supportedAddedSubmissionLocales from supportedSubmissionLocales and supportedFormLocales
            $submFormLocales = $union(json_decode($submissionLocales), json_decode($formLocales));
            $insert($localeId, 'supportedAddedSubmissionLocales', $submFormLocales);
            // supportedSubmissionMetadataLocales from supportedSubmissionLocales and supportedFormLocales
            $insert($localeId, 'supportedSubmissionMetadataLocales', $submFormLocales);
        }

        /**
         * Update locale character lengths
         */
        // Cache will be cleared at the end of I9707_WeblateUILocales
        $key = 'localeUpdates1 day';
        $tableLocaleColumns = Cache::remember($key, DateInterval::createFromDateString('1 day'), function () {
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
            $localeColumns->each(function ($column) use ($tableName, $isPostgres) {
                $default = match($isPostgres) {
                    // PostgreSQL describes defaults in terms like: 'en'::character varying
                    // If there is a '-delimited string part, fetch and use it. If it's just
                    // "::character varying", it was an empty string.
                    true => empty($column['default']) ? null : explode("'", $column['default'])[1],
                    // For MariaDB > 10.11.13, defaults may be wrapped in ' due to MDEV-13132. If so,
                    // trim quotes from string defaults.
                    false => is_string($column['default']) ? trim($column['default'], "'") : $column['default'],
                };
                Schema::table($tableName, fn (Blueprint $table) => $table->string($column['name'], 28)->nullable($column['nullable'])->default($default)->change());
            });
        }
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
