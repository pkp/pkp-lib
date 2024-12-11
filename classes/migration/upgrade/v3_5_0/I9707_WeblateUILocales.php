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
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I9707_WeblateUILocales extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $localesTable = [
            'be@cyrillic' => 'be',
            'bs' => 'bs_Latn',
            'fr_FR' => 'fr',
            'nb' => 'nb_NO',
            'sr@cyrillic' => 'sr_Cyrl',
            'sr@latin' => 'sr_Latn',
            'uz@cyrillic' => 'uz',
            'uz@latin' => 'uz_Latn',
            'zh_CN' => 'zh_Hans',
        ];

        $schemaLocName = (DB::connection() instanceof PostgresConnection) ? 'TABLE_CATALOG' : 'TABLE_SCHEMA';
        $renameLocale = fn (string $l) => collect(DB::select("SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE COLUMN_NAME = ? AND {$schemaLocName} = ?", [$l, DB::connection()->getDatabaseName()]))
            ->each(function (\stdClass $sc) use ($l, $localesTable) {
                foreach ($localesTable as $uiLocale => $weblateLocale) {
                    DB::table($sc->TABLE_NAME ?? $sc->table_name)->where($l, '=', $uiLocale)->update([$l => $weblateLocale]);
                }
            });

        $renameLocale('primary_locale');
        $renameLocale('locale');
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
