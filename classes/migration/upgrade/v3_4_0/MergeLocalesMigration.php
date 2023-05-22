<?php

/**
 * @file classes/migration/upgrade/v3_4_0/MergeLocalesMigration.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class MergeLocalesMigration
 *
 * @brief Change Locales from locale_countryCode localization folder notation to locale localization folder notation
 */

namespace PKP\migration\upgrade\v3_4_0;

use Exception;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        foreach ($this->getSettingsTables() as $tableName => [$entityIdColumnName, $primaryKeyColumnName]) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, 'locale')) {
                continue;
            }

            foreach (self::getAffectedLocales() as $source => $target) {
                DB::table($tableName)
                    ->where('locale', $source)
                    ->update(['locale' => $target]);
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

        Schema::table('site', function (Blueprint $table) {
            $table->string('installed_locales')->default('en')->change();
        });

        // users
        $migration = $this;
        $users = DB::table('users')->chunkById(1000, function ($users) use ($migration) {
            foreach ($users as $user) {
                $migration->updateArrayLocale($user->locales, 'users', 'locales', 'user_id', $user->user_id);
            }
        }, 'user_id');

        // submissions
        $submissions = DB::table('submissions')->chunkById(1000, function ($submissions) use ($migration) {
            foreach ($submissions as $submission) {
                $migration->updateSingleValueLocale($submission->locale, 'submissions', 'locale', 'submission_id', $submission->submission_id);
            }
        }, 'submission_id');


        // email_templates_default_data
        $emailTemplatesDefaultData = DB::table('email_templates_default_data')
            ->get();

        foreach ($emailTemplatesDefaultData as $emailTemplatesDefaultDataCurrent) {
            $this->updateSingleValueLocaleEmailData($emailTemplatesDefaultDataCurrent->locale, 'email_templates_default_data', $emailTemplatesDefaultDataCurrent->email_key, $emailTemplatesDefaultData);
        }

        Schema::table('email_templates_default_data', function (Blueprint $table) {
            $table->string('locale')->default('en')->change();
        });

        // Context
        $contexts = DB::table($this->CONTEXT_TABLE)
            ->get();

        foreach ($contexts as $context) {
            $this->updateSingleValueLocale($context->primary_locale, $this->CONTEXT_TABLE, 'primary_locale', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }

        $contextSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedFormLocales')
            ->get();

        foreach ($contextSettingsFormLocales as $contextSettingsFormLocale) {
            $this->updateArrayLocaleSetting($contextSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedFormLocales', $this->CONTEXT_COLUMN, $contextSettingsFormLocale->{$this->CONTEXT_COLUMN});
        }

        $contextSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedLocales')
            ->get();

        foreach ($contextSettingsFormLocales as $contextSettingsFormLocale) {
            $this->updateArrayLocaleSetting($contextSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedLocales', $this->CONTEXT_COLUMN, $contextSettingsFormLocale->{$this->CONTEXT_COLUMN});
        }

        $contextSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedSubmissionLocales')
            ->get();

        foreach ($contextSettingsFormLocales as $contextSettingsFormLocale) {
            $this->updateArrayLocaleSetting($contextSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedSubmissionLocales', $this->CONTEXT_COLUMN, $contextSettingsFormLocale->{$this->CONTEXT_COLUMN});
        }
    }

    public function updateArrayLocaleNoId(string $dbLocales, string $table, string $column)
    {
        $siteSupportedLocales = json_decode($dbLocales) ?? [];

        if ($siteSupportedLocales !== false) {
            $newLocales = [];
            foreach ($siteSupportedLocales as $siteSupportedLocale) {
                $updatedLocale = $this->getUpdatedLocale($siteSupportedLocale);

                if (!is_null($updatedLocale)) {
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

    public function updateArrayLocale(string $dbLocales, string $table, string $column, string $tableKeyColumn, int $id)
    {
        $siteSupportedLocales = json_decode($dbLocales) ?? [];

        $newLocales = [];
        foreach ($siteSupportedLocales as $siteSupportedLocale) {
            $updatedLocale = $this->getUpdatedLocale($siteSupportedLocale);

            if (!is_null($updatedLocale)) {
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

    public function updateArrayLocaleSetting(string $dbLocales, string $table, string $settingValue, string $tableKeyColumn, int $id)
    {
        $siteSupportedLocales = json_decode($dbLocales) ?? [];

        if ($siteSupportedLocales !== false) {
            $newLocales = [];
            foreach ($siteSupportedLocales as $siteSupportedLocale) {
                $updatedLocale = $this->getUpdatedLocale($siteSupportedLocale);

                if (!is_null($updatedLocale)) {
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

    public function updateSingleValueLocale(?string $localevalue, string $table, string $column, string $tableKeyColumn, int $id)
    {
        if ($localevalue === null) {
            return;
        }

        $updatedLocale = $this->getUpdatedLocale($localevalue);

        if (!is_null($updatedLocale)) {
            DB::table($table)
                ->where($tableKeyColumn, '=', $id)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    public function updateSingleValueLocaleNoId(string $localevalue, string $table, string $column)
    {
        $updatedLocale = $this->getUpdatedLocale($localevalue);

        if (!is_null($updatedLocale)) {
            DB::table($table)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    public function updateSingleValueLocaleEmailData(string $localevalue, string $table, string $email_key, Collection $allEmailTemplateData)
    {
        $localeRows = DB::table($table)
            ->where('email_key', '=', $email_key)
            ->where('locale', '=', $localevalue);

        $stillExists = $localeRows->exists();

        if ($stillExists) {
            $updatedLocale = $this->getUpdatedLocale($localevalue);

            // if this is null we should do nothing - we are not handling this locale
            if (!is_null($updatedLocale)) {
                // if the updatedLocale is the same as the setting's locale we should do nothing
                // Check if the database already has an updated locale with the same value -
                $hasAlreadyExistingUpdatedLocale = DB::table($table)
                    ->where('email_key', '=', $email_key)
                    ->where('locale', '=', $updatedLocale)
                    ->exists();

                // if so, it is safe to delete the currently processed value.
                if ($hasAlreadyExistingUpdatedLocale) {
                    $localeRows->delete();
                } else {
                    $localeRows->update(['locale' => $updatedLocale]);
                }
            }
        }
    }

    /**
     * Returns null if no conversion is available or
     * a key value pair collection that the key is the output locale and the value is the defaultLocale.
     */
    public function getUpdatedLocale(string $localeValue): ?string
    {
        $affectedLocales = $this->getAffectedLocales();

        if ($affectedLocales->keys()->contains($localeValue)) {
            $localeTransformation = $affectedLocales[$localeValue];

            return $localeTransformation;
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

    /**
     * Get a list of settings tables, keyed by table name. Values are [entity_id_column_name, settings_table_id_column_name].
     */
    protected static function getSettingsTables(): Collection
    {
        return collect([
            'announcement_settings' => ['announcement_id', 'announcement_setting_id'],
            'announcement_type_settings' => ['type_id', 'announcement_type_setting_id'],
            'author_settings' => ['author_id', 'author_setting_id'],
            'category_settings' => ['category_id', 'category_setting_id'],
            'citation_settings' => ['citation_id', 'citation_setting_id'],
            'controlled_vocab_entry_settings' => ['controlled_vocab_entry_id', 'controlled_vocab_entry_setting_id'],
            'data_object_tombstone_settings' => ['tombstone_id', 'tombstone_setting_id'],
            'email_templates_settings' => ['email_id', 'email_template_setting_id'],
            'event_log_settings' => ['log_id', 'event_log_setting_id'],
            'filter_settings' => ['filter_id', 'filter_setting_id'],
            'genre_settings' => ['genre_id', 'genre_setting_id'],
            'library_file_settings' => ['file_id', 'library_file_setting_id'],
            'navigation_menu_item_assignment_settings' => ['navigation_menu_item_assignment_id', 'navigation_menu_item_assignment_setting_id'],
            'navigation_menu_item_settings' => ['navigation_menu_item_id', 'navigation_menu_item_setting_id'],
            'notification_settings' => ['notification_id', 'notification_setting_id'],
            'notification_subscription_settings' => ['setting_id', 'notification_subscription_setting_id'],
            'plugin_settings' => ['context_id', 'plugin_setting_id'],
            'publication_settings' => ['publication_id', 'publication_setting_id'],
            'review_form_element_settings' => ['review_form_element_id', 'review_form_element_setting_id'],
            'review_form_settings' => ['review_form_id', 'review_form_setting_id'],
            'submission_file_settings' => ['submission_file_id', 'submission_file_setting_id'],
            'submission_settings' => ['submission_id', 'submission_setting_id'],
            'user_group_settings' => ['user_group_id', 'user_group_setting_id'],
            'user_settings' => ['user_id', 'user_setting_id'],
            'site_settings' => [null, 'site_setting_id'],
            'funder_settings' => ['funder_id', 'funder_setting_id'],
            'funder_award_settings' => ['funder_award_id', 'funder_award_setting_id'],
            'static_page_settings' => ['static_page_id', 'static_page_setting_id'],
        ]);
    }

    /**
     * Returns the effected locales along with the corresponding rename for each
     */
    public static function getAffectedLocales(): Collection
    {
        return collect([
            'es_ES' => 'es',
            'en_US' => 'en',
            'sr_RS@cyrillic' => 'sr@cyrillic',
            'sr_RS@latin' => 'sr@latin',
            'el_GR' => 'el',
            'de_DE' => 'de',
            'da_DK' => 'da',
            'cs_CZ' => 'cs',
            'ca_ES' => 'ca',
            'bs_BA' => 'bs',
            'bg_BG' => 'bg',
            'be_BY@cyrillic' => 'be@cyrillic',
            'az_AZ' => 'az',
            'ar_IQ' => 'ar',
            'fa_IR' => 'fa',
            'fi_FI' => 'fi',
            'gd_GB' => 'gd',
            'gl_ES' => 'gl',
            'he_IL' => 'he',
            'hi_IN' => 'hi',
            'hr_HR' => 'hr',
            'hu_HU' => 'hu',
            'hy_AM' => 'hy',
            'id_ID' => 'id',
            'is_IS' => 'is',
            'it_IT' => 'it',
            'ja_JP' => 'ja',
            'ka_GE' => 'ka',
            'kk_KZ' => 'kk',
            'ko_KR' => 'ko',
            'ku_IQ' => 'ku',
            'lt_LT' => 'lt',
            'lv_LV' => 'lv',
            'mk_MK' => 'mk',
            'mn_MN' => 'mn',
            'ms_MY' => 'ms',
            'nb_NO' => 'nb',
            'nl_NL' => 'nl',
            'pl_PL' => 'pl',
            'ro_RO' => 'ro',
            'ru_RU' => 'ru',
            'si_LK' => 'si',
            'sk_SK' => 'sk',
            'sl_SI' => 'sl',
            'sv_SE' => 'sv',
            'tr_TR' => 'tr',
            'uk_UA' => 'uk',
            'ur_PK' => 'ur',
            'uz_UZ@cyrillic' => 'uz@cyrillic',
            'uz_UZ@latin' => 'uz@latin',
            'vi_VN' => 'vi',
            'eu_ES' => 'eu',
            'sw_KE' => 'sw',
            'zh_TW' => 'zh_Hant'
        ]);
    }
}
