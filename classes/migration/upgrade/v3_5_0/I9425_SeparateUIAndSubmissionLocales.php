<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9425_SeparateUIAndSubmissionLocales.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9425_SeparateUIAndSubmissionLocales
 *
 * @brief pkp/pkp-lib#9425 Make submission language selection and metadata forms independent from website language settings
 */

namespace PKP\migration\upgrade\v3_5_0;

use Illuminate\Database\PostgresConnection;
use Illuminate\Database\Schema\Blueprint;
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

        $schemaLocName = (DB::connection() instanceof PostgresConnection)
            ? 'TABLE_CATALOG'
            : 'TABLE_SCHEMA';

        $updateLength = fn (string $l) => collect(
            DB::select(
                "
                SELECT DISTINCT TABLE_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE COLUMN_NAME = ? AND {$schemaLocName} = ?",
                [$l, DB::connection()->getDatabaseName()]
            )
        )->each(
            fn (\stdClass $sc) => Schema::table(
                $sc->TABLE_NAME ?? $sc->table_name,
                fn (Blueprint $table) => collect(Schema::getColumns($sc->TABLE_NAME ?? $sc->table_name))
                    ->where('name', $l)
                    ->first(default:[])['nullable'] ?? false
                            ? $table->string($l, 28)->nullable()->change()
                            : $table->string($l, 28)->change()
            )
        );

        $updateLength('primary_locale');
        $updateLength('locale');

    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
