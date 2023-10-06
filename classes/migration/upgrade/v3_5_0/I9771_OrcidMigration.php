<?php

/**
 * @file classes/migration/upgrade/v3_5_0/I9771_OrcidMigration.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class I9771_OrcidMigration
 *
 * @brief Move ORCID integration settings from plugin to core application
 */

namespace PKP\migration\upgrade\v3_5_0;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PKP\config\Config;
use PKP\install\DowngradeNotSupportedException;
use PKP\migration\Migration;
use function Clue\StreamFilter\fun;

class I9771_OrcidMigration extends Migration
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
    private string $settingsTableName;
    private string $settingsTableKey;

    /**
     * @inheritDoc
     */
    public function up(): void
    {
        $applicationName = Application::get()->getName();
        $this->settingsTableName = self::CONTEXT_SETTING_TABLE_NAMES[$applicationName];
        $this->settingsTableKey = self::CONTEXT_SETTING_TABLE_KEYS[$applicationName];

        $this->movePluginSettings();
        $this->moveSiteSettings();
        $this->installEmailTemplates();
        $this->markVerifiedOrcids();
    }

    /**
     * @inheritDoc
     * @throws DowngradeNotSupportedException
     */
    public function down(): void
    {
        throw new DowngradeNotSupportedException();
    }

    /**
     * Add plugin settings as context settings
     */
    private function movePluginSettings(): void
    {
        $q = DB::table('plugin_settings')
            ->where('plugin_name', '=', 'orcidprofileplugin')
            ->where('context_id', '<>', 0)
            ->select(['context_id', 'setting_name', 'setting_value']);

        $results = $q->get();
        $mappedResults = $results->map(function ($item) {
            if (!Str::startsWith($item->setting_name, 'orcid')) {
                $item->setting_name = 'orcid' . Str::ucfirst($item->setting_name);
            }

            // Handle conversion from raw API URL to API type
            if ($item->setting_name === 'orcidProfileAPIPath') {
                $item->setting_name = 'orcidApiType';
                $item->setting_value = $this->apiUrlToApiType($item->setting_value);
            }

            $item->{$this->settingsTableKey} = $item->context_id;
            unset($item->context_id);

            return (array)$item;
        })
            ->filter(function ($item) {
                return in_array($item['setting_name'], [
                    'orcidApiType',
                    'orcidCity',
                    'orcidClientId',
                    'orcidClientSecret',
                    'orcidEnabled',
                    'orcidLogLevel',
                    'orcidSendMailToAuthorsOnPublication',
                ]);
            });

        DB::table($this->settingsTableName)
            ->insert($mappedResults->toArray());

        DB::table('plugin_settings')
            ->where('plugin_name', '=', 'orcidprofileplugin')
            ->delete();
    }

    /**
     * Migrate site settings based on config file values.
     */
    private function moveSiteSettings(): void
    {
        $globalClientId = Config::getVar('orcid', 'client_id', '');
        $globalClientSecret = Config::getVar('orcid', 'client_secret', '');
        $globalApiUrl = Config::getVar('orcid', 'api_url', '');

        if (empty($globalClientId) || empty($globalClientSecret) || empty($globalApiUrl)) {
            return;
        }

        $settings = collect();
        $settings->put('orcidEnabled', 1);
        $settings->put('orcidClientId', $globalClientId);
        $settings->put('orcidClientSecret', $globalClientSecret);
        $settings->put('orcidApiType', $this->apiUrlToApiType($globalApiUrl));

        $siteSettings = $settings->reduce(function ($carry, $value, $key) {
            $carry[] = [
                'setting_name' => $key,
                'setting_value' => $value,
            ];

            return $carry;
        }, []);

        DB::table('site_settings')
            ->insert($siteSettings);
    }

    /**
     * Ensure email templates are installed
     */
    private function installEmailTemplates(): void
    {
        Repo::emailTemplate()->dao->installEmailTemplates(
            Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            [],
            'ORCID_COLLECT_AUTHOR_ID',
            true,
        );
        Repo::emailTemplate()->dao->installEmailTemplates(
            Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(),
            [],
            'ORCID_REQUEST_AUTHOR_AUTHORIZATION',
            true,
        );
    }

    /**
     * Mark user authenticated/verified ORCIDs as such in the database.
     */
    private function markVerifiedOrcids(): void
    {
        $tables = [
            ['name' => 'author_settings', 'id' => 'author_id'],
            ['name' => 'user_settings', 'id' => 'user_id'],
        ];

        foreach ($tables as $tableInfo) {
            $results = DB::table($tableInfo['name'])
                ->whereIn('setting_name', ['orcid', 'orcidAccessToken'])
                ->whereNot('setting_value', '=', '')
                ->get()
                ->reduce(function (array $carry, \stdClass $item) use ($tableInfo) {
                    $carry[$item->{$tableInfo['id']}][$item->setting_name] = $item->setting_value;

                    return $carry;
                }, []);


            /** @var Collection $insertValues */
            $insertValues = collect($results)
                ->filter(function (array $item) {
                    if (empty($item["orcid"]) || empty($item["orcidAccessToken"])) {
                        return false;
                    }

                    return true;
                })
                ->map(function(array $item, int $key) use ($tableInfo) {
                    return [
                        $tableInfo['id'] => $key,
                        'setting_name' => 'orcidIsVerified',
                        'setting_value' => true,
                    ];
                });

            if ($insertValues->isNotEmpty()) {
                DB::table($tableInfo['name'])
                    ->insert($insertValues->toArray());
            }
        }
    }

    /**
     * Maps an API URL to the API type now used internally.
     */
    private function apiUrlToApiType(string $apiUrl): string
    {
        return match ($apiUrl) {
            'https://pub.orcid.org/', 'https://orcid.org/' => 'publicProduction',
            'https://pub.sandbox.orcid.org/', 'https://sandbox.orcid.org/' => 'publicSandbox',
            'https://api.orcid.org/' => 'memberProduction',
            'https://api.sandbox.orcid.org/' => 'memberSandbox',
        };
    }
}
