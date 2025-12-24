<?php

/**
 * @file classes/migration/upgrade/v3_6_0/I12074_EncryptCredentials.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I12074_EncryptCredentials
 *
 * @brief Encrypt sensitive credentials stored in database settings tables
 */

namespace PKP\migration\upgrade\v3_6_0;

use APP\core\Application;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;

class I12074_EncryptCredentials extends Migration
{
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
     * @inheritDoc
     */
    public function up(): void
    {
        $this->encryptContextSettings();
        $this->encryptPluginSettings();
    }

    /**
     * @inheritDoc
     *
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        try {
            $this->decryptContextSettings();
            $this->decryptPluginSettings();
        } catch (DecryptException $e) {
            throw new DowngradeNotSupportedException($e->getMessage());
        }
    }

    /**
     * Encrypt orcidClientSecret in context settings table
     */
    private function encryptContextSettings(): void
    {
        $applicationName = Application::get()->getName();
        $settingsTableName = self::CONTEXT_SETTING_TABLE_NAMES[$applicationName];
        $settingsTableKey = self::CONTEXT_SETTING_TABLE_KEYS[$applicationName];

        $this->encryptSettingValues(
            $settingsTableName,
            $settingsTableKey,
            'orcidClientSecret'
        );
    }

    /**
     * Encrypt plugin credentials in plugin_settings table
     */
    private function encryptPluginSettings(): void
    {
        // DataCite plugin: password and testPassword
        $this->encryptPluginSettingValues('dataciteplugin', ['password', 'testPassword']);

        // Crossref plugin: password
        $this->encryptPluginSettingValues('crossrefplugin', ['password']);
    }

    /**
     * Decrypt orcidClientSecret in context settings table
     */
    private function decryptContextSettings(): void
    {
        $applicationName = Application::get()->getName();
        $settingsTableName = self::CONTEXT_SETTING_TABLE_NAMES[$applicationName];
        $settingsTableKey = self::CONTEXT_SETTING_TABLE_KEYS[$applicationName];

        $this->decryptSettingValues(
            $settingsTableName,
            $settingsTableKey,
            'orcidClientSecret'
        );
    }

    /**
     * Decrypt plugin credentials in plugin_settings table
     */
    private function decryptPluginSettings(): void
    {
        // DataCite plugin: password and testPassword
        $this->decryptPluginSettingValues('dataciteplugin', ['password', 'testPassword']);

        // Crossref plugin: password
        $this->decryptPluginSettingValues('crossrefplugin', ['password']);
    }

    /**
     * Encrypt setting values in a settings table
     */
    private function encryptSettingValues(string $tableName, string $primaryKey, string $settingName): void
    {
        $rows = DB::table($tableName)
            ->where('setting_name', $settingName)
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->get([$primaryKey, 'setting_value']);

        foreach ($rows as $row) {
            // Skip if already encrypted (starts with eyJ which is base64 for {"iv":)
            // if (str_starts_with($row->setting_value, 'eyJ')) {
            //     continue;
            // }

            DB::table($tableName)
                ->where($primaryKey, $row->{$primaryKey})
                ->where('setting_name', $settingName)
                ->update(['setting_value' => Crypt::encrypt($row->setting_value)]);
        }
    }

    /**
     * Decrypt setting values in a settings table
     */
    private function decryptSettingValues(string $tableName, string $primaryKey, string $settingName): void
    {
        $rows = DB::table($tableName)
            ->where('setting_name', $settingName)
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->get([$primaryKey, 'setting_value']);

        foreach ($rows as $row) {
            // Skip if not encrypted (doesn't start with eyJ)
            if (!str_starts_with($row->setting_value, 'eyJ')) {
                continue;
            }

            DB::table($tableName)
                ->where($primaryKey, $row->{$primaryKey})
                ->where('setting_name', $settingName)
                ->update(['setting_value' => Crypt::decrypt($row->setting_value)]);
        }
    }

    /**
     * Encrypt plugin setting values
     */
    private function encryptPluginSettingValues(string $pluginName, array $settingNames): void
    {
        $rows = DB::table('plugin_settings')
            ->where('plugin_name', $pluginName)
            ->whereIn('setting_name', $settingNames)
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->get(['context_id', 'setting_name', 'setting_value']);

        foreach ($rows as $row) {
            // Skip if already encrypted (starts with eyJ which is base64 for {"iv":)
            // if (str_starts_with($row->setting_value, 'eyJ')) {
            //     continue;
            // }

            DB::table('plugin_settings')
                ->where('plugin_name', $pluginName)
                ->where('context_id', $row->context_id)
                ->where('setting_name', $row->setting_name)
                ->update(['setting_value' => Crypt::encrypt($row->setting_value)]);
        }
    }

    /**
     * Decrypt plugin setting values
     */
    private function decryptPluginSettingValues(string $pluginName, array $settingNames): void
    {
        $rows = DB::table('plugin_settings')
            ->where('plugin_name', $pluginName)
            ->whereIn('setting_name', $settingNames)
            ->whereNotNull('setting_value')
            ->where('setting_value', '<>', '')
            ->get(['context_id', 'setting_name', 'setting_value']);

        foreach ($rows as $row) {
            // Skip if not encrypted (doesn't start with eyJ)
            if (!str_starts_with($row->setting_value, 'eyJ')) {
                continue;
            }

            DB::table('plugin_settings')
                ->where('plugin_name', $pluginName)
                ->where('context_id', $row->context_id)
                ->where('setting_name', $row->setting_name)
                ->update(['setting_value' => Crypt::decrypt($row->setting_value)]);
        }
    }
}