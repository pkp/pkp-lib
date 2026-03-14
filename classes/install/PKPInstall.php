<?php

/**
 * @defgroup install Install
 * Implements a software installer, including a flexible upgrader that can
 * manage schema changes, data representation changes, etc.
 */

/**
 * @file classes/install/PKPInstall.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInstall
 *
 * @ingroup install
 *
 * @see Installer, InstallForm
 *
 * @brief Perform system installation.
 *
 * This script will:
 *  - Create the database (optionally), and install the database tables and initial data.
 *  - Update the config file with installation parameters.
 */

namespace PKP\install;

use APP\core\Application;
use APP\facades\Repo;
use DateTime;
use Exception;
use Illuminate\Database\MariaDbConnection;
use Illuminate\Database\MySqlConnection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Config as FacadesConfig;
use Illuminate\Support\Facades\DB;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPContainer;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\file\FileManager;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\site\SiteDAO;
use PKP\site\Version;
use PKP\userGroup\UserGroup;

class PKPInstall extends Installer
{
    /** @var int Minimum password length */
    public const MIN_PASSWORD_LENGTH = 6;

    /**
     * Returns true iff this is an upgrade process.
     */
    public function isUpgrade()
    {
        return false;
    }

    // Legacy defaults when config is missing (upgrade consistency)
    public const LEGACY_CHARSET = 'utf8';
    public const LEGACY_MYSQL_COLLATION = 'utf8_general_ci';

    // Recommended defaults for new installs (full Unicode / emoji support)
    public const DEFAULT_CHARSET = 'utf8mb4';
    public const DEFAULT_MYSQL_COLLATION = 'utf8mb4_unicode_ci';

    /**
     * Pre-installation.
     *
     * @return bool
     */
    public function preInstall()
    {
        if (!isset($this->currentVersion)) {
            $this->currentVersion = Version::fromString('');
        }

        $this->locale = $this->getParam('locale');
        $this->installedLocales = $this->getParam('additionalLocales');
        if (!isset($this->installedLocales) || !is_array($this->installedLocales)) {
            $this->installedLocales = [];
        }
        if (!in_array($this->locale, $this->installedLocales) && Locale::isLocaleValid($this->locale)) {
            array_push($this->installedLocales, $this->locale);
        }

        // Map valid config options to Illuminate database drivers
        $driver = PKPContainer::getDatabaseDriverName(strtolower($this->getParam('databaseDriver')));

        $config = FacadesConfig::get('database');
        $config['default'] = $driver;

        $connection = array_merge([
            'driver' => $driver,
            'host' => $this->getParam('databaseHost'),
            'port' => $this->getParam('databasePort'),
            'unix_socket' => $this->getParam('unixSocket'),
            'database' => $this->getParam('databaseName'),
            'username' => $this->getParam('databaseUsername'),
            'password' => $this->getParam('databasePassword'),
        ], self::resolveConnectionParams(
            $driver,
            Config::getVar('i18n', 'connection_charset'),
            Config::getVar('database', 'collation')
        ));

        $config['connections'][$driver] = $connection;

        FacadesConfig::set('database', $config);

        // Need to register the `DatabaseServiceProvider` as when the `SessionServiceProvider`
        // registers itself in the `\PKP\core\PKPContainer::registerConfiguredProviders`, it
        // registers an instance of `\Illuminate\Database\ConnectionInterface` which contains the
        // initial details from the `config.inc.php` rather than what is set through the install form.
        app()->register(new \Illuminate\Database\DatabaseServiceProvider(app()));

        $result = parent::preInstall();

        if ($this->getParam('timeZone')) {
            $this->initializeDatabaseTimeZone($this->getParam('timeZone'));
        }

        return $result;
    }

    /**
     * Resolve and validate the DB connection charset and collation for a given driver.
     *
     * Single authoritative function for all charset/collation logic:
     * - Normalizes charset (lowercased, trimmed)
     * - PostgreSQL: maps any utf8* variant to 'utf8'; returns null collation
     *   (encoding is a DB-level property on PostgreSQL, not a connection param)
     * - MySQL / MariaDB: pairs charset with collation and auto-resolves mismatches
     *   in either direction to keep the pair compatible:
     *     utf8 + utf8mb4_*  → charset upgraded to utf8mb4 (collation wins)
     *     utf8mb4 + utf8_*  → collation upgraded to utf8mb4_unicode_ci (charset wins)
     *
     * @param string  $driver       Normalized driver name (mysql|mysqli|mariadb|pgsql)
     * @param ?string $rawCharset   Raw charset from config (e.g. 'utf8mb4', 'utf8', null)
     * @param ?string $rawCollation Raw collation from config (MySQL/MariaDB only; pass null for pgsql)
     *
     * @return array{charset: string, collation?: string}
     */
    public static function resolveConnectionParams(
        string $driver,
        ?string $rawCharset,
        ?string $rawCollation = null
    ): array {
        // Normalize charset
        if ($rawCharset === null || trim($rawCharset) === '') {
            $charset = self::LEGACY_CHARSET;
        } else {
            $charset = strtolower(trim($rawCharset));
        }

        // PostgreSQL: always map utf8* variants to 'utf8'; collation is not a per-connection
        // setting in PostgreSQL so it is not included in the returned array.
        if ($driver === 'pgsql') {
            if (str_starts_with($charset, 'utf8')) {
                $charset = self::LEGACY_CHARSET;
            }
            return ['charset' => $charset];
        }

        // MySQL / MariaDB: determine collation and resolve any charset/collation mismatch.
        // Two mismatches are possible — both are auto-resolved so the pair is always compatible:
        //
        //   utf8 charset  + utf8mb4_* collation  → upgrade charset  to utf8mb4 (collation first)
        //   utf8mb4 charset + utf8_* collation   → upgrade collation to utf8mb4_unicode_ci (charset first)
        $collation = $rawCollation ?? self::LEGACY_MYSQL_COLLATION;

        if ($charset === self::LEGACY_CHARSET && str_starts_with($collation, self::DEFAULT_CHARSET . '_')) {
            // utf8 + utf8mb4_* → upgrade charset
            $charset = self::DEFAULT_CHARSET;
        } elseif ($charset === self::DEFAULT_CHARSET
            && str_starts_with($collation, self::LEGACY_CHARSET . '_')
            && !str_starts_with($collation, self::DEFAULT_CHARSET . '_')
        ) {
            // utf8mb4 + utf8_* (e.g. utf8_general_ci) → upgrade collation
            $collation = self::DEFAULT_MYSQL_COLLATION;
        }

        return ['charset' => $charset, 'collation' => $collation];
    }

    //
    // Installer actions
    //

    /**
     * Get the names of the directories to create.
     *
     * @return array
     */
    public function getCreateDirectories()
    {
        return ['site'];
    }

    /**
     * Create required files directories
     * FIXME No longer needed since FileManager will auto-create?
     *
     * @return bool
     */
    public function createDirectories()
    {
        // Check if files directory exists and is writeable
        if (!(file_exists($this->getParam('filesDir')) && is_writeable($this->getParam('filesDir')))) {
            // Files upload directory unusable
            $this->setError(Installer::INSTALLER_ERROR_GENERAL, 'installer.installFilesDirError');
            return false;
        } else {
            // Create required subdirectories
            $dirsToCreate = $this->getCreateDirectories();
            $dirsToCreate[] = 'usageStats';
            $fileManager = new FileManager();
            foreach ($dirsToCreate as $dirName) {
                $dirToCreate = $this->getParam('filesDir') . '/' . $dirName;
                if (!file_exists($dirToCreate)) {
                    if (!$fileManager->mkdir($dirToCreate)) {
                        $this->setError(Installer::INSTALLER_ERROR_GENERAL, 'installer.installFilesDirError');
                        return false;
                    }
                }
            }
        }

        // Check if public files directory exists and is writeable
        $publicFilesDir = Config::getVar('files', 'public_files_dir');
        if (!(file_exists($publicFilesDir) && is_writeable($publicFilesDir))) {
            // Public files upload directory unusable
            $this->setError(Installer::INSTALLER_ERROR_GENERAL, 'installer.publicFilesDirError');
            return false;
        } else {
            // Create required subdirectories
            $dirsToCreate = $this->getCreateDirectories();
            $fileManager = new FileManager();
            foreach ($dirsToCreate as $dirName) {
                $dirToCreate = $publicFilesDir . '/' . $dirName;
                if (!file_exists($dirToCreate)) {
                    if (!$fileManager->mkdir($dirToCreate)) {
                        $this->setError(Installer::INSTALLER_ERROR_GENERAL, 'installer.publicFilesDirError');
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Write the configuration file.
     *
     * @return bool
     */
    public function createConfig()
    {
        $request = Application::get()->getRequest();

        // Normalize driver so resolveConnectionParams() applies correct DB-specific rules.
        $driver = PKPContainer::getDatabaseDriverName(strtolower($this->getParam('databaseDriver')));

        $connectionParams = self::resolveConnectionParams(
            $driver,
            Config::getVar('i18n', 'connection_charset'),
            Config::getVar('database', 'collation')
        );

        $databaseParams = array_merge([
            'driver' => $this->getParam('databaseDriver'),
            'host' => $this->getParam('databaseHost'),
            'username' => $this->getParam('databaseUsername'),
            'password' => $this->getParam('databasePassword'),
            'name' => $this->getParam('databaseName'),
        ], $connectionParams);

        return $this->updateConfig(
            [
                'general' => [
                    'app_key' => \PKP\core\PKPAppKey::generate(),
                    'installed' => 'On',
                    'base_url' => $request->getBaseUrl(),
                    'enable_beacon' => $this->getParam('enableBeacon') ? 'On' : 'Off',
                    'allowed_hosts' => json_encode([$request->getServerHost(null, false)]),
                    'time_zone' => $this->getParam('timeZone')
                ],
                'database' => $databaseParams,
                'i18n' => [
                    'locale' => $this->getParam('locale'),
                    'connection_charset' => $connectionParams['charset'],
                ],
                'files' => [
                    'files_dir' => $this->getParam('filesDir')
                ],
                'oai' => [
                    'repository_id' => $this->getParam('oaiRepositoryId')
                ]
            ]
        );
    }

    /**
     * Create initial required data.
     *
     * @return bool
     */
    public function createData()
    {
        $siteLocale = $this->getParam('locale');

        // Add initial site administrator user
        $user = Repo::user()->newDataObject();
        $user->setUsername($this->getParam('adminUsername'));
        $user->setPassword(Validation::encryptCredentials($this->getParam('adminUsername'), $this->getParam('adminPassword'), $this->getParam('encryption')));
        $user->setGivenName($user->getUsername(), $siteLocale);
        $user->setFamilyName($user->getUsername(), $siteLocale);
        $user->setEmail($this->getParam('adminEmail'));
        $user->setDateRegistered(Core::getCurrentDate());
        $user->setInlineHelp(1);
        Repo::user()->add($user);

        // Prepare multilingual 'name' and 'namePlural' settings
        $names = [];
        $namePlurals = [];
        foreach ($this->installedLocales as $locale) {
            $names[$locale] = __('default.groups.name.siteAdmin', [], $locale);
            $namePlurals[$locale] = __('default.groups.plural.siteAdmin', [], $locale);
        }

        // Create an admin user group
        $adminUserGroup = new UserGroup([
            'roleId' => Role::ROLE_ID_SITE_ADMIN,
            'contextId' => \PKP\core\PKPApplication::SITE_CONTEXT_ID,
            'isDefault' => true,
            'permitSettings' => true,
            'name' => $names,
            'namePlural' => $namePlurals,
        ]);

        // Save the UserGroup to the database
        $adminUserGroup->save();

        // Assign the user to the admin user group
        Repo::userGroup()->assignUserToGroup($user->getId(), $adminUserGroup->id);

        // Add initial site data
        /** @var SiteDAO $siteDao */
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->newDataObject();
        $site->setRedirect(null);
        $site->setMinPasswordLength(static::MIN_PASSWORD_LENGTH);
        $site->setPrimaryLocale($siteLocale);
        $site->setInstalledLocales($this->installedLocales);
        $site->setSupportedLocales($this->installedLocales);
        $site->setUniqueSiteID(PKPString::generateUUID());
        $siteDao->insertSite($site);

        Repo::emailTemplate()->dao->installEmailTemplates(Repo::emailTemplate()->dao->getMainEmailTemplatesFilename(), $this->installedLocales);

        // Install default site settings
        $schemaService = app()->get('schema');
        $site = $schemaService->setDefaults(PKPSchemaService::SCHEMA_SITE, $site, $site->getSupportedLocales(), $site->getPrimaryLocale());
        $site->setData('contactEmail', $this->getParam('adminEmail'), $site->getPrimaryLocale());
        $siteDao->updateObject($site);

        return true;
    }

    /**
     * Initialize the database timezone settings during installation
     *
     * @param string $timeZone The selected timezone from the installation form
     */
    protected function initializeDatabaseTimeZone(string $timeZone): void
    {
        try {
            date_default_timezone_set($timeZone ?: ini_get('date.timezone') ?: 'UTC');

            // Set the current offset for this timezone
            $offset = (new DateTime())->format('P');

            // Set the timezone based on the database type
            $statement = match (true) {
                DB::connection() instanceof MySqlConnection,
                DB::connection() instanceof MariaDbConnection
                    => "SET time_zone = '{$offset}'",
                DB::connection() instanceof PostgresConnection
                    => "SET TIME ZONE INTERVAL '{$offset}' HOUR TO MINUTE"
            };

            DB::statement($statement);
        } catch (Exception $e) {
            $this->setError(INSTALLER_ERROR_DB, 'Failed to set database timezone: ' . $e->getMessage());
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\PKPInstall', '\PKPInstall');
}
