<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I12513_MissingSiteLocaleUserNames.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12513_MissingSiteLocaleUserNames
 *
 * @brief Copy givenName/familyName to the site primary locale for users who are missing it.
 *
 * The invitation flow introduced in 3.5.0 did not copy the invitee's name to the site primary
 * locale when it differed from the context primary locale. getFullName() falls back to the site
 * primary locale, so affected users appeared without a name in many parts of the UI.
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\core\Application;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12513_MissingSiteLocaleUserNames extends Migration
{
    public function up(): void
    {
        $sitePrimaryLocale = DB::table('site')->value('primary_locale');
        if (!$sitePrimaryLocale) {
            return;
        }

        $settingNames = ['givenName', 'familyName'];

        // Remove empty/null site-locale entries so they can be filled from another locale.
        DB::table('user_settings')
            ->where('locale', $sitePrimaryLocale)
            ->whereIn('setting_name', $settingNames)
            ->where(function ($query) {
                $query->where('setting_value', '')->orWhereNull('setting_value');
            })
            ->delete();

        $contextDao = Application::getContextDAO();
        $contextTable = $contextDao->tableName;
        $contextPrimaryKey = $contextDao->primaryKeyColumn;

        $sitePrimaryLocaleEscaped = DB::getPdo()->quote($sitePrimaryLocale);

        // Step 1: Users who have a non-empty givenName in some locale but not in the site primary
        // locale. givenName is always required, so this is the sole condition for needing a fix;
        // a missing familyName alongside an existing site-locale givenName is defective data
        // outside the scope of this migration.
        $affectedUsers = DB::table('user_settings AS us_gn')
            ->select('us_gn.user_id')
            ->leftJoin('user_settings AS us_site_gn', function ($join) use ($sitePrimaryLocale) {
                $join->on('us_site_gn.user_id', '=', 'us_gn.user_id')
                    ->where('us_site_gn.locale', '=', $sitePrimaryLocale)
                    ->where('us_site_gn.setting_name', '=', 'givenName');
            })
            ->where('us_gn.setting_name', '=', 'givenName')
            ->where('us_gn.locale', '!=', $sitePrimaryLocale)
            ->where('us_gn.setting_value', '!=', '')
            ->whereNotNull('us_gn.setting_value')
            ->whereNull('us_site_gn.user_id')
            ->distinct();

        // Step 2: For each affected user, determine the source locale to copy names from.
        // The context primary locale is preferred (most likely to match what the invitee entered);
        // any locale with a non-empty givenName is the fallback. Both givenName and familyName will
        // be copied from this single locale so they stay paired.
        $contextLocales = DB::table('user_user_groups AS uug')
            ->select('uug.user_id', 'c.primary_locale')
            ->join('user_groups AS ug', 'ug.user_group_id', '=', 'uug.user_group_id')
            ->join("{$contextTable} AS c", "c.{$contextPrimaryKey}", '=', 'ug.context_id')
            ->where('c.primary_locale', '!=', $sitePrimaryLocale)
            ->distinct();

        $sourceLocales = DB::table('user_settings AS us_gn')
            ->select(
                'us_gn.user_id',
                DB::raw('COALESCE(MIN(CASE WHEN us_gn.locale = c.primary_locale THEN us_gn.locale END), MIN(us_gn.locale)) AS source_locale')
            )
            ->joinSub($affectedUsers, 'affected', 'affected.user_id', '=', 'us_gn.user_id')
            ->leftJoinSub($contextLocales, 'c', 'c.user_id', '=', 'us_gn.user_id')
            ->where('us_gn.setting_name', '=', 'givenName')
            ->where('us_gn.locale', '!=', $sitePrimaryLocale)
            ->where('us_gn.setting_value', '!=', '')
            ->whereNotNull('us_gn.setting_value')
            ->groupBy('us_gn.user_id');

        // Step 3: Copy givenName and familyName from the source locale.
        // familyName is only inserted when non-empty in the source locale; it is never sourced
        // from a different locale even if the source locale has no family name.
        DB::table('user_settings')->insertUsing(
            ['user_id', 'locale', 'setting_name', 'setting_value'],
            DB::table('user_settings AS us')
                ->select(
                    'us.user_id',
                    DB::raw("{$sitePrimaryLocaleEscaped} AS locale"),
                    'us.setting_name',
                    'us.setting_value'
                )
                ->joinSub($sourceLocales, 'src', function ($join) {
                    $join->on('src.user_id', '=', 'us.user_id')
                        ->on('us.locale', '=', 'src.source_locale');
                })
                ->leftJoin('user_settings AS us_site', function ($join) use ($sitePrimaryLocale) {
                    $join->on('us_site.user_id', '=', 'us.user_id')
                        ->where('us_site.locale', '=', $sitePrimaryLocale)
                        ->whereColumn('us_site.setting_name', 'us.setting_name');
                })
                ->whereIn('us.setting_name', $settingNames)
                ->where('us.setting_value', '!=', '')
                ->whereNotNull('us.setting_value')
                ->whereNull('us_site.user_id')
        );
    }

    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }
}
