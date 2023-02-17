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
        foreach ($settingsTables as $settingsTable => $settingsTableIdColumn) {
            if (Schema::hasTable($settingsTable) && Schema::hasColumn($settingsTable, 'locale')) {
                $settingsValues = DB::table($settingsTable)
                    ->select(['locale', 'setting_name', 'setting_value'])
                    ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn) {
                        return $query->addSelect($settingsTableIdColumn);
                    })
                    ->get();
                
                foreach ($settingsValues as $settingsValue) {
                    $stillExists = DB::table($settingsTable)
                        ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                            return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                        })
                        ->where('setting_name', '=', $settingsValue->setting_name)
                        ->where('locale', '=', $settingsValue->locale)
                        ->exists();
                    
                    // if it does not exist we should do nothing
                    if ($stillExists) {
                        $updatedLocaleRet = $this->getUpdatedLocale($settingsValue->locale);

                        // if this is null we should do nothing - we are not handling this locale
                        if (!is_null($updatedLocaleRet)) {
                            $updatedLocale = $updatedLocaleRet->keys()->first();
                            $defaultLocale = $updatedLocaleRet->get($updatedLocale);

                            // if the updatedLocale is the same as the setting's locale we should do nothing
                            if ($updatedLocale != $settingsValue->locale) {
                                
                                // Check if the database already has an updated locale with the same value -
                                $hasAlreadyExistingUpdatedLocale = DB::table($settingsTable)
                                    ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                                        return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                                    })
                                    ->where('setting_name', '=', $settingsValue->setting_name)
                                    ->where('locale', '=', $updatedLocale)
                                    ->where('setting_value', '=', $settingsValue->setting_value)
                                    ->exists();
                                
                                // if so, it is safe to delete the currently processed value.
                                if ($hasAlreadyExistingUpdatedLocale) {
                                    DB::table($settingsTable)
                                        ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                                            return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                                        })
                                        ->where('setting_name', '=', $settingsValue->setting_name)
                                        ->where('locale', '=', $settingsValue->locale)
                                        ->delete();
                                } else {
                                    // If we are managing the defaultLocale then we can update the value to the $updatedLocale
                                    if ($defaultLocale == $settingsValue->locale) {
                                        DB::table($settingsTable)
                                            ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                                                return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                                            })
                                            ->where('setting_name', '=', $settingsValue->setting_name)
                                            ->where('locale', '=', $settingsValue->locale)
                                            ->update(['locale' => $updatedLocale]);
                                    } else {
                                        // If we are not managing the defaultLocale

                                        // we must first check if there is the default locale in the dataset
                                        $hasExistingDefaultLocale = $settingsValues
                                            ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                                                return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                                            })
                                            ->where('setting_name', '=', $settingsValue->setting_name)
                                            ->where('locale', '=', $defaultLocale)
                                            ->exists();

                                        // if the dataset does not have the defaultLocale, then we can update to the $updatedLocale
                                        if (!$hasExistingDefaultLocale) {
                                            DB::table($settingsTable)
                                                ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                                                    return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                                                })
                                                ->where('setting_name', '=', $settingsValue->setting_name)
                                                ->where('locale', '=', $settingsValue->locale)
                                                ->update(['locale' => $updatedLocale]);
                                        } else {
                                            // if the dataset does have the defaultLocale, we are going to delete this locale in favor of the default
                                            DB::table($settingsTable)
                                                ->when(!is_null($settingsTableIdColumn), function ($query) use ($settingsTableIdColumn, $settingsValue) {
                                                    return $query->where($settingsTableIdColumn, '=', $settingsValue->{$settingsTableIdColumn});
                                                })
                                                ->where('setting_name', '=', $settingsValue->setting_name)
                                                ->where('locale', '=', $settingsValue->locale)
                                                ->delete();
                                        }
                                    }
                                }
                            }
                        }
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

        Schema::table('site', function (Blueprint $table) {
            $table->string('installed_locales')->default('en')->change();
        });
        
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
            $this->updateArrayLocaleSetting($contextSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedFormLocales', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }

        $contextSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedLocales')
            ->get();

        foreach ($contextSettingsFormLocales as $contextSettingsFormLocale) {
            $this->updateArrayLocaleSetting($contextSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedLocales', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }

        $contextSettingsFormLocales = DB::table($this->CONTEXT_SETTINGS_TABLE)
            ->where('setting_name', '=', 'supportedSubmissionLocales')
            ->get();

        foreach ($contextSettingsFormLocales as $contextSettingsFormLocale) {
            $this->updateArrayLocaleSetting($contextSettingsFormLocale->setting_value, $this->CONTEXT_SETTINGS_TABLE, 'supportedSubmissionLocales', $this->CONTEXT_COLUMN, $context->{$this->CONTEXT_COLUMN});
        }
    }

    function updateArrayLocaleNoId(string $dbLocales, string $table, string $column) 
    {
        $siteSupportedLocales = json_decode($dbLocales);

        if ($siteSupportedLocales !== false) {
            $newLocales = [];
            foreach ($siteSupportedLocales as $siteSupportedLocale) {
                $updatedLocaleRet = $this->getUpdatedLocale($siteSupportedLocale);
                
                if (!is_null($updatedLocaleRet)) {
                    $updatedLocale = $updatedLocaleRet->keys()->first();

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
                $updatedLocaleRet = $this->getUpdatedLocale($siteSupportedLocale);

                if (!is_null($updatedLocaleRet)) {
                    $updatedLocale = $updatedLocaleRet->keys()->first();

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
                $updatedLocaleRet = $this->getUpdatedLocale($siteSupportedLocale);

                if (!is_null($updatedLocaleRet)) {
                    $updatedLocale = $updatedLocaleRet->keys()->first();

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
        $updatedLocaleRet = $this->getUpdatedLocale($localevalue);
        
        if (!is_null($updatedLocaleRet)) {
            $updatedLocale = $updatedLocaleRet->keys()->first();

            DB::table($table)
                ->where($tableKeyColumn, '=', $id)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    function updateSingleValueLocaleNoId(string $localevalue, string $table, string $column) 
    {
        $updatedLocaleRet = $this->getUpdatedLocale($localevalue);
        
        if (!is_null($updatedLocaleRet)) {
            $updatedLocale = $updatedLocaleRet->keys()->first();

            DB::table($table)
                ->update([
                    $column => $updatedLocale
                ]);
        }
    }

    function updateSingleValueLocaleEmailData(string $localevalue, string $table, string $email_key, Collection $allEmailTemplateData) 
    {
        $stillExists = DB::table($table)
            ->where('email_key', '=', $email_key)
            ->where('locale', '=', $localevalue)
            ->exists();
        
        if ($stillExists) {
            $updatedLocaleRet = $this->getUpdatedLocale($localevalue);

            // if this is null we should do nothing - we are not handling this locale
            if (!is_null($updatedLocaleRet)) {
                $updatedLocale = $updatedLocaleRet->keys()->first();
                $defaultLocale = $updatedLocaleRet->get($updatedLocale);

                // if the updatedLocale is the same as the setting's locale we should do nothing
                if ($updatedLocale != $localevalue) {
                    // Check if the database already has an updated locale with the same value -
                    $hasAlreadyExistingUpdatedLocale = DB::table($table)
                        ->where('email_key', '=', $email_key)
                        ->where('locale', '=', $updatedLocale)
                        ->exists();
                    
                    // if so, it is safe to delete the currently processed value.
                    if ($hasAlreadyExistingUpdatedLocale) {
                        DB::table($table)
                            ->where('email_key', '=', $email_key)
                            ->where('locale', '=', $localevalue)
                            ->delete();
                    } else {
                        // If we are managing the defaultLocale then we can update the value to the $updatedLocale
                        if ($defaultLocale == $localevalue) {
                            DB::table($table)
                                ->where('email_key', '=', $email_key)
                                ->where('locale', '=', $localevalue)
                                ->update(['locale' => $updatedLocale]);
                        } else {
                            // If we are not managing the defaultLocale

                            // we must first check if there is the default locale in the dataset
                            $hasExistingDefaultLocale = $allEmailTemplateData
                                ->where('email_key', '=', $email_key)
                                ->where('locale', '=', $defaultLocale)
                                ->exists();

                            // if the dataset does not have the defaultLocale, then we can update to the $updatedLocale
                            if (!$hasExistingDefaultLocale) {
                                DB::table($table)
                                    ->where('email_key', '=', $email_key)
                                    ->where('locale', '=', $localevalue)
                                    ->update(['locale' => $updatedLocale]);
                            } else {
                                // if the dataset does have the defaultLocale, we are going to delete this locale in favor of the default
                                DB::table($table)
                                    ->where('email_key', '=', $email_key)
                                    ->where('locale', '=', $localevalue)
                                    ->delete();
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * Returns null if no conversion is available or 
     * a key value pair collection that the key is the output locale and the value is the defaultLocale.
     */ 
    function getUpdatedLocale(string $localeValue) : ?Collection 
    {
        $affectedLocales = $this->getAffectedLocales();

        $localeParts = explode('_', $localeValue);
        $localeCode = $localeParts[0];

        if ($affectedLocales->keys()->contains($localeValue) || $affectedLocales->keys()->contains($localeCode)) {
            $localeTransformation = $affectedLocales->get($localeValue);
            if (is_null($localeTransformation)) {
                $localeTransformation = $affectedLocales->get($localeCode);
            }

            if ($localeTransformation instanceof Collection) {
                $defaultLocale = $affectedLocales->get($localeCode)->first();

                // Check for cases like code_XX@latin
                $extension = "";
                if (strpos($localeValue, '@') !== false) {
                    $extension = substr($localeValue, strpos($localeValue, '@'));
                }

                return collect(["${localeCode}${extension}" => "${defaultLocale}${extension}"]);
            } else {
                return collect([$localeTransformation => $localeTransformation]);
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

    protected static function getSettingsTables(): Collection
    {
        return collect([
            'announcement_settings' => 'announcement_id',
            'announcement_type_settings' => 'type_id',
            'author_settings' => 'author_id',
            'category_settings' => 'category_id',
            'citation_settings' => 'citation_id',
            'controlled_vocab_entry_settings' => 'controlled_vocab_entry_id',
            'data_object_tombstone_settings' => 'tombstone_id',
            'doi_settings' => 'doi_id',
            'email_templates_settings' => 'email_id',
            'event_log_settings' => 'log_id',
            'filter_settings' => 'filter_id',
            'genre_settings' => 'genre_id',
            'institution_settings' => 'institution_id',
            'library_file_settings' => 'file_id',
            'navigation_menu_item_assignment_settings' => 'navigation_menu_item_assignment_id',
            'navigation_menu_item_settings' => 'navigation_menu_item_id',
            'notification_settings' => 'notification_id',
            'notification_subscription_settings' => 'setting_id',
            'plugin_settings' => 'context_id',
            'publication_settings' => 'publication_id',
            'review_form_element_settings' => 'review_form_element_id',
            'review_form_settings' => 'review_form_id',
            'submission_file_settings' => 'submission_file_id',
            'submission_settings' => 'submission_id',
            'user_group_settings' => 'user_group_id',
            'user_settings' => 'user_id',
            'site_settings' => null
        ]);
    }

    public static function getAffectedLocales(): Collection
    {
        return collect([
            'es' => collect(['es_ES']),
            'en' => collect(['en_US']),
            'sr' => collect(['sr_RS']),
            'el' => collect(['el_GR']),
            'de' => collect(['de_DE']),
            'da' => collect(['da_DK']),
            'cs' => collect(['cs_CZ']),
            'ca' => collect(['ca_ES']),
            'bs' => collect(['bs_BA']),
            'bg' => collect(['bg_BG']),
            'be' => collect(['be_BY']),
            'az' => collect(['az_AZ']),
            'ar' => collect(['ar_IQ']),
            'fa' => collect(['fa_IR']),
            'fi' => collect(['fi_FI']),
            'gd' => collect(['gd_GB']),
            'gl' => collect(['gl_ES']),
            'he' => collect(['he_IL']),
            'hi' => collect(['hi_IN']),
            'hr' => collect(['hr_HR']),
            'hu' => collect(['hu_HU']),
            'hy' => collect(['hy_AM']),
            'id' => collect(['id_ID']),
            'is' => collect(['is_IS']),
            'it' => collect(['it_IT']),
            'ja' => collect(['ja_JP']),
            'ka' => collect(['ka_GE']),
            'kk' => collect(['kk_KZ']),
            'ko' => collect(['ko_KR']),
            'ku' => collect(['ku_IQ']),
            'lt' => collect(['lt_LT']),
            'lv' => collect(['lv_LV']),
            'mk' => collect(['mk_MK']),
            'mn' => collect(['mn_MN']),
            'ms' => collect(['ms_MY']),
            'nb' => collect(['nb_NO']),
            'nl' => collect(['nl_NL']),
            'pl' => collect(['pl_PL']),
            'ro' => collect(['ro_RO']),
            'ru' => collect(['ru_RU']),
            'si' => collect(['si_LK']),
            'sk' => collect(['sk_SK']),
            'sl' => collect(['sl_SI']),
            'sv' => collect(['sv_SE']),
            'tr' => collect(['tr_TR']),
            'uk' => collect(['uk_UA']),
            'ur' => collect(['ur_PK']),
            'uz' => collect(['uz_UZ']),
            'vi' => collect(['vi_VN']),
            'eu' => collect(['eu_ES']),
            'sw' => collect(['sw_KE']),
            'zh_TW' => 'zh_Hant'
        ]);
    }
}
