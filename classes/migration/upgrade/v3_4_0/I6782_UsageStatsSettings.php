<?php

/**
 * @file classes/migration/upgrade/v3_4_0/I6782_UsageStatsSettings.php
 *
 * Copyright (c) 2022 Simon Fraser University
 * Copyright (c) 2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I6782_UsageStatsSettings
 * @brief Migrate usage stats settings.
 */

namespace PKP\migration\upgrade\v3_4_0;

use APP\core\Services;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use PKP\plugins\PluginRegistry;

class I6782_UsageStatsSettings extends Migration
{
    /**
     * Run the migration.
     */
    public function up(): void
    {
        // Read old usage stats settings
        // Geo data stats settings
        $optionalColumns = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'usagestatsplugin')
            ->where('setting_name', '=', 'optionalColumns')
            ->value('setting_value');

        $enableGeoUsageStats = 'disabled';
        $keepDailyUsageStats = false;
        if (!is_null($optionalColumns)) {
            $keepDailyUsageStats = true;
            if (str_contains($optionalColumns, 'city')) {
                $enableGeoUsageStats = 'country+region+city';
            } elseif (str_contains($optionalColumns, 'region')) {
                $enableGeoUsageStats = 'country+region';
            } else {
                $enableGeoUsageStats = 'country';
            }
        }
        // Compress archives settings
        $compressArchives = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'usagestatsplugin')
            ->where('setting_name', '=', 'compressArchives')
            ->value('setting_value');
        // Migrate site settings
        DB::table('site_settings')->insertOrIgnore([
            ['setting_name' => 'compressStatsLogs', 'setting_value' => $compressArchives],
            ['setting_name' => 'enableGeoUsageStats', 'setting_value' => $enableGeoUsageStats],
            ['setting_name' => 'keepDailyUsageStats', 'setting_value' => $keepDailyUsageStats]
        ]);

        // Display site settings
        $displayStatistics = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'usagestatsplugin')
            ->where('setting_name', '=', 'displayStatistics')
            ->where('context_id', '=', 0)
            ->value('setting_value');
        $chartType = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'usagestatsplugin')
            ->where('setting_name', '=', 'chartType')
            ->where('context_id', '=', 0)
            ->value('setting_value');
        // Migrate usage stats site display settings to the active site theme
        $siteThemePlugins = PluginRegistry::getPlugins('themes');
        $activeSiteTheme = null;
        foreach ($siteThemePlugins as $siteThemePlugin) {
            if ($siteThemePlugin->isActive()) {
                $activeSiteTheme = $siteThemePlugin;
                break;
            }
        }
        if (isset($activeSiteTheme)) {
            $siteUsageStatsDisplay = !$displayStatistics ? 'none' : $chartType;
            DB::table('plugin_settings')->insertOrIgnore([
                ['plugin_name' => $activeSiteTheme->getName(), 'context_id' => 0, 'setting_name' => 'displayStats', 'setting_value' => $siteUsageStatsDisplay, 'setting_type' => 'string'],
            ]);
        }

        // Migrate context settings
        // Get all, also disabled, contexts
        $contextIds = Services::get('context')->getIds();
        foreach ($contextIds as $contextId) {
            $contextDisplayStatistics = $contextChartType = null;
            $contextDisplayStatistics = DB::table('plugin_settings')
                ->where('plugin_name', '=', 'usagestatsplugin')
                ->where('setting_name', '=', 'displayStatistics')
                ->where('context_id', '=', $contextId)
                ->value('setting_value');
            $contextChartType = DB::table('plugin_settings')
                ->where('plugin_name', '=', 'usagestatsplugin')
                ->where('setting_name', '=', 'chartType')
                ->where('context_id', '=', $contextId)
                ->value('setting_value');
            // Migrate usage stats display settings to the active context theme
            $contextThemePlugins = PluginRegistry::loadCategory('themes', true, $contextId);
            $activeContextTheme = null;
            foreach ($contextThemePlugins as $contextThemePlugin) {
                if ($contextThemePlugin->isActive()) {
                    $activeContextTheme = $contextThemePlugin;
                    break;
                }
            }
            if (isset($activeContextTheme)) {
                $contextUsageStatsDisplay = !$contextDisplayStatistics ? 'none' : $contextChartType;
                DB::table('plugin_settings')->insertOrIgnore([
                    ['plugin_name' => $activeContextTheme->getName(), 'context_id' => $contextId, 'setting_name' => 'displayStats', 'setting_value' => $contextUsageStatsDisplay, 'setting_type' => 'string'],
                ]);
            }
        }
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
}
