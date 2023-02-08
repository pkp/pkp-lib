<?php

/**
 * @file classes/migration/upgrade/v3_4_0/MergeLocalesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MergeLocalesMigration
 * @brief Change Locales from locale_countryCode localization folder notation to locale localization folder notation
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PKP\install\DowngradeNotSupportedException;

abstract class MergeLocalesMigration extends \PKP\migration\Migration
{
    protected string $CONTEXT_TABLE = '';
    protected string $CONTEXT_SETTINGS_TABLE = '';
    protected string $CONTEXT_COLUMN = '';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (empty($this->CONTEXT_TABLE) || empty($this->CONTEXT_SETTINGS_TABLE) || empty($this->CONTEXT_COLUMN)) {
            throw new Exception('Upgrade could not be completed because required properties for the MergeLocalesMigration migration are undefined.');
        }

        // All _settings tables.
        $settingsTables = $this->getSettingsTables();
        foreach($settingsTables as $settingsTable) {
            if (Schema::hasColumn($settingsTable, 'locale')) {
                $affectedLocales = $this->getAffectedLocales();
                foreach($affectedLocales as $affectedLocale => $toLocale) {
                    if (!isset($toLocale)) {
                        DB::table($settingsTable)
                            ->where('locale', 'like', $affectedLocale . '_%')
                            ->update(['locale' => $affectedLocale]);
                    } else {
                        DB::table($settingsTable)
                            ->where('locale', 'like', $affectedLocale)
                            ->update(['locale' => $toLocale]);
                    }
                    
                }
            }
        }

        // Tables
        // site
        $site = DB::table('site')
            ->select(['supported_locales', 'installed_locales', 'primary_locale'])
            ->first();

        $this->updateArrayLocaleNoId($site->supported_locales, 'site', 'supported_locales');
        $this->updateArrayLocaleNoId($site->installed_locales, 'site', 'installed_locales');
        $this->updateSingleValueLocaleNoId($site->primary_locale, 'site', 'primary_locale');
        
        // users
        $users = DB::table('users')
            ->get();

        foreach ($users as $user) {
            $this->updateArrayLocale($user->locales, 'users', 'locales', 'user_id', $user->user_id);
        }

        // submissions
        $submissions = DB::table('submissions')
            ->get();

        foreach ($submissions as $submission) {
            $this->updateSingleValueLocale($submission->locale, 'submissions', 'locale', 'submission_id', $submission->submission_id);
        }

        // email_templates_default_data
        $emailTemplatesDefaultData = DB::table('email_templates_default_data')
            ->get();

        foreach ($emailTemplatesDefaultData as $emailTemplatesDefaultDataCurrent) {
            $this->updateSingleValueLocaleEmailData($emailTemplatesDefaultDataCurrent->locale, 'email_templates_default_data', 'locale', 'email_key', $emailTemplatesDefaultDataCurrent->email_key);
        }

        // Context
        $contexts = DB::table($this->CONTEXT_TABLE)
            ->get();

        foreach ($contexts as $context) {
            $this->updateSingleValueLocale($context->primary_locale, $this->CONTEXT_TABLE, 'primary_locale', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }

        $journalSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedFormLocales')
            ->get();

        foreach ($journalSettingsFormLocales as $journalSettingsFormLocale) {
            $this->updateArrayLocaleSetting($journalSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedFormLocales', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }

        $journalSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedLocales')
            ->get();

        foreach ($journalSettingsFormLocales as $journalSettingsFormLocale) {
            $this->updateArrayLocaleSetting($journalSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedLocales', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }

        $journalSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedSubmissionLocales')
            ->get();

        foreach ($journalSettingsFormLocales as $journalSettingsFormLocale) {
            $this->updateArrayLocaleSetting($journalSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedSubmissionLocales', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }
    }

    function updateArrayLocaleNoId(string $dbLocales, string $table, string $column) 
    {
        $siteSupportedLocales = json_decode($dbLocales);

        if ($siteSupportedLocales !== false) {
            $newLocales = [];
            foreach ($siteSupportedLocales as $siteSupportedLocale) {
                $updatedLocale = $this->getUpdatedLocale($siteSupportedLocale);
                
                if ($updatedLocale) {
                    if (!in_array($updatedLocale, $newLocales)) {
                        $newLocales[] = $updatedLocale;
                    }
                } else {
                    $newLocales[] = $siteSupportedLocale;
                }
            }

            DB::table($table)
                ->update([
                    $column => $newLocales
                ]);
        }
    }

    function updateArrayLocale(string $dbLocales, string $table, string $column, string $tableKeyColumn, int $id) 
    {
        $siteSupportedLocales = json_decode($dbLocales);

        if ($siteSupportedLocales !== false) {
            $newLocales = [];
            foreach ($siteSupportedLocales as $siteSupportedLocale) {
                $updatedLocale = $this->getUpdatedLocale($siteSupportedLocale);

                if ($updatedLocale) {
                    if (!in_array($updatedLocale, $newLocales)) {
                        $newLocales[] = $updatedLocale;
                    }
                } else {
                    $newLocales[] = $siteSupportedLocale;
                }
            }

            DB::table($table)
                ->where($tableKeyColumn, '=', $id)
                ->update([
                    $column => $newLocales
                ]);
        }
    }

    function updateArrayLocaleSetting(string $dbLocales, string $table, string $settingValue, string $tableKeyColumn, int $id) 
    {
        $siteSupportedLocales = json_decode($dbLocales);

        if ($siteSupportedLocales !== false) {
            $newLocales = [];
            foreach ($siteSupportedLocales as $siteSupportedLocale) {
                $updatedLocale = $this->getUpdatedLocale($siteSupportedLocale);

                if ($updatedLocale) {
                    if (!in_array($updatedLocale, $newLocales)) {
                        $newLocales[] = $updatedLocale;
                    }
                } else {
                    $newLocales[] = $siteSupportedLocale;
                }
            }

            DB::table($table)
                ->where($tableKeyColumn, '=', $id)
                ->where('setting_name', '=', $settingValue)
                ->update([
                    'setting_value' => $newLocales
                ]);
        }
    }

    function updateSingleValueLocale(string $localevalue, string $table, string $column, string $tableKeyColumn, int $id) 
    {
        $updatedLocale = $this->getUpdatedLocale($localevalue);

        if ($updatedLocale) {
            DB::table($table)
                ->where($tableKeyColumn, '=', $id)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    function updateSingleValueLocaleNoId(string $localevalue, string $table, string $column) 
    {
        $updatedLocale = $this->getUpdatedLocale($localevalue);

        if ($updatedLocale) {
            DB::table($table)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    function updateSingleValueLocaleEmailData(string $localevalue, string $table, string $column, string $tableKeyColumn, string $id) 
    {
        $updatedLocale = $this->getUpdatedLocale($localevalue);

        if ($updatedLocale) {
            DB::table($table)
                ->where($tableKeyColumn, '=', $id)
                ->where($column, '=', $localevalue)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    function getUpdatedLocale(string $localeValue) : ?string 
    {
        $affectedLocales = $this->getAffectedLocales();

        if ($affectedLocales->keys()->contains($localeValue)) {
            return $affectedLocales->get($localeValue);
        } else {
            $localeCode = substr($localeValue, 0, 2);

            if ($affectedLocales->keys()->contains($localeCode)) {
                if ($affectedLocales->get($localeCode) == null) {
                    $extension = "";
                    if (strpos($localeValue, '@') !== false) {
                        $extension = substr($localeValue, strpos($localeValue, '@'));
                    }
                    return $localeCode . $extension;
                } else {
                    return $affectedLocales->get($localeCode);
                }
            }
        }

        return null;
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

    protected function getSettingsTables(): Collection
    {
        return collect([
            'announcement_settings',
            'announcement_type_settings',
            'author_settings',
            'category_settings',
            'citation_settings',
            'controlled_vocab_entry_settings',
            'data_object_tombstone_settings',
            'doi_settings',
            'email_templates_settings',
            'filter_settings',
            'genre_settings',
            'institution_settings',
            'library_file_settings',
            'navigation_menu_item_assignment_settings',
            'navigation_menu_item_settings',
            'notification_settings',
            'notification_subscription_settings',
            'plugin_settings',
            'publication_settings',
            'review_form_element_settings',
            'review_form_settings',
            'site_settings',
            'submission_file_settings',
            'submission_settings',
            'user_group_settings',
            'user_settings'
        ]);
    }

    protected function getAffectedLocales(): Collection
    {
        return collect([
            'es' => null,
            'en' => null,
            'sr' => null,
            'el' => null,
            'de' => null,
            'da' => null,
            'cs' => null,
            'ca' => null,
            'bs' => null,
            'bg' => null,
            'be' => null,
            'az' => null,
            'ar' => null,
            'fa' => null,
            'fi' => null,
            'gd' => null,
            'gl' => null,
            'he' => null,
            'hi' => null,
            'hr' => null,
            'hu' => null,
            'hy' => null,
            'id' => null,
            'is' => null,
            'it' => null,
            'ja' => null,
            'ka' => null,
            'kk' => null,
            'ko' => null,
            'ku' => null,
            'lt' => null,
            'lv' => null,
            'mk' => null,
            'mn' => null,
            'ms' => null,
            'nb' => null,
            'nl' => null,
            'pl' => null,
            'ro' => null,
            'ru' => null,
            'si' => null,
            'sk' => null,
            'sl' => null,
            'sv' => null,
            'tr' => null,
            'uk' => null,
            'ur' => null,
            'uz' => null,
            'vi' => null,
            'eu' => null,
            'sw' => null,
            'zh_TW' => 'zh_Hant'
        ]);
    }
}
