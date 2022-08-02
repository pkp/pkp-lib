<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I7624_StrftimeDeprecation.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I7624_StrftimeDeprecation
 * @brief Convert strftime-based date formats into DateTime::format instead.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\core\PKPString;

class I7624_StrftimeDeprecation extends \PKP\migration\Migration
{
    private const DATETIME_SETTINGS = [
        'dateFormatShort',
        'dateFormatLong',
        'timeFormat',
        'datetimeFormatShort',
        'datetimeFormatLong',
    ];

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
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->convert('up');
    }

    /**
     * Reverse the downgrades
     */
    public function down(): void
    {
        $this->convert('down');
    }

    /**
     * Convert the date format settings.
     *
     * @param direction string 'up'|'down'
     */
    private function convert(string $direction)
    {
        $applicationName = Application::get()->getName();
        $contextIdColumnName = self::CONTEXT_SETTING_TABLE_KEYS[$applicationName];
        $dateSettingValues = DB::table(self::CONTEXT_SETTING_TABLE_NAMES[$applicationName])
            ->whereIn('setting_name', self::DATETIME_SETTINGS)
            ->select(['setting_value', $contextIdColumnName, 'setting_name', 'locale'])
            ->get();
        $map = PKPString::getStrftimeConversion();
        switch ($direction) {
            case 'up': break;
            case 'down': $map = array_flip($map);
                break;
            default: throw new \Exception("Unknown direction ${direction}");
        }
        foreach ($dateSettingValues as $row) {
            DB::table(self::CONTEXT_SETTING_TABLE_NAMES[$applicationName])
                ->where('setting_name', '=', $row->setting_name)
                ->where('locale', '=', $row->locale)
                ->where($contextIdColumnName, '=', $row->$contextIdColumnName)
                ->update(
                    [
                        'setting_value' => strtr($row->setting_value, $map)
                    ]
                );
        }
    }
}
