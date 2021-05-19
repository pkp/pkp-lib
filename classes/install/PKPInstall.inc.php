<?php

/**
 * @defgroup install Install
 * Implements a software installer, including a flexible upgrader that can
 * manage schema changes, data representation changes, etc.
 */

/**
 * @file classes/install/PKPInstall.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Install
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
use APP\core\Services;
use APP\i18n\AppLocale;

use Illuminate\Config\Repository;
use Illuminate\Database\DatabaseServiceProvider;
use Illuminate\Events\EventServiceProvider;
use PKP\config\Config;
use PKP\core\PKPContainer;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\security\Role;

use PKP\security\Validation;
use PKP\services\PKPSchemaService;
use PKP\site\Version;

class PKPInstall extends Installer
{
    /**
     * Constructor.
     *
     * @see install.form.InstallForm for the expected parameters
     *
     * @param $xmlDescriptor string descriptor path
     * @param $params array installer parameters
     * @param $isPlugin boolean true iff a plugin is being installed
     */
    public function __construct($xmlDescriptor, $params, $isPlugin)
    {
        parent::__construct($xmlDescriptor, $params, $isPlugin);
    }

    /**
     * Returns true iff this is an upgrade process.
     */
    public function isUpgrade()
    {
        return false;
    }

    /**
     * Pre-installation.
     *
     * @return boolean
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
        if (!in_array($this->locale, $this->installedLocales) && AppLocale::isLocaleValid($this->locale)) {
            array_push($this->installedLocales, $this->locale);
        }

        // Map valid config options to Illuminate database drivers
        $driver = strtolower($this->getParam('databaseDriver'));
        $connectionCharset = $this->getParam('connectionCharset');
        if (substr($driver, 0, 8) === 'postgres') {
            $driver = 'pgsql';
        } else {
            $driver = 'mysql';
        }

        $items['database']['default'] = $driver;
        $items['database']['connections'][$driver] = [
            'driver' => $driver,
            'host' => $this->getParam('databaseHost'),
            'database' => $this->getParam('databaseName'),
            'username' => $this->getParam('databaseUsername'),
            'password' => $this->getParam('databasePassword'),
            'charset' => $connectionCharset == 'latin1' ? 'latin1' : 'utf8',
            'collation' => 'utf8_general_ci',
        ];

        try {
            // Register database and related services in the container
            $laravelContainer = PKPContainer::getInstance();
            $laravelContainer->instance('config', new Repository($items));
            $laravelContainer->register(new EventServiceProvider($laravelContainer));
            $laravelContainer->register(new DatabaseServiceProvider($laravelContainer));
        } catch (Exception $e) {
            $this->setError(Installer::INSTALLER_ERROR_DB, $e->getMessage());
            return false;
        }

        return parent::preInstall();
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
     * @return boolean
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
     * @return boolean
     */
    public function createConfig()
    {
        $request = Application::get()->getRequest();
        return $this->updateConfig(
            [
                'general' => [
                    'installed' => 'On',
                    'base_url' => $request->getBaseUrl(),
                    'enable_beacon' => $this->getParam('enableBeacon') ? 'On' : 'Off',
                ],
                'database' => [
                    'driver' => $this->getParam('databaseDriver'),
                    'host' => $this->getParam('databaseHost'),
                    'username' => $this->getParam('databaseUsername'),
                    'password' => $this->getParam('databasePassword'),
                    'name' => $this->getParam('databaseName')
                ],
                'i18n' => [
                    'locale' => $this->getParam('locale'),
                    'client_charset' => $this->getParam('clientCharset'),
                    'connection_charset' => $this->getParam('connectionCharset') == '' ? 'Off' : $this->getParam('connectionCharset'),
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
     * @return boolean
     */
    public function createData()
    {
        $siteLocale = $this->getParam('locale');

        // Add initial site administrator user
        $userDao = DAORegistry::getDAO('UserDAO');
        $user = $userDao->newDataObject();
        $user->setUsername($this->getParam('adminUsername'));
        $user->setPassword(Validation::encryptCredentials($this->getParam('adminUsername'), $this->getParam('adminPassword'), $this->getParam('encryption')));
        $user->setGivenName($user->getUsername(), $siteLocale);
        $user->setFamilyName($user->getUsername(), $siteLocale);
        $user->setEmail($this->getParam('adminEmail'));
        $user->setInlineHelp(1);
        $userDao->insertObject($user);

        // Create an admin user group
        AppLocale::requireComponents(LOCALE_COMPONENT_PKP_DEFAULT);
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
        $adminUserGroup = $userGroupDao->newDataObject();
        $adminUserGroup->setRoleId(Role::ROLE_ID_SITE_ADMIN);
        $adminUserGroup->setContextId(\PKP\core\PKPApplication::CONTEXT_ID_NONE);
        $adminUserGroup->setDefault(true);
        foreach ($this->installedLocales as $locale) {
            $name = __('default.groups.name.siteAdmin', [], $locale);
            $namePlural = __('default.groups.plural.siteAdmin', [], $locale);
            $adminUserGroup->setData('name', $name, $locale);
            $adminUserGroup->setData('namePlural', $namePlural, $locale);
        }
        $userGroupDao->insertObject($adminUserGroup);

        // Put the installer into this user group
        $userGroupDao->assignUserToGroup($user->getId(), $adminUserGroup->getId());

        // Add initial site data
        $siteDao = DAORegistry::getDAO('SiteDAO');
        $site = $siteDao->newDataObject();
        $site->setRedirect(0);
        $site->setMinPasswordLength(INSTALLER_DEFAULT_MIN_PASSWORD_LENGTH);
        $site->setPrimaryLocale($siteLocale);
        $site->setInstalledLocales($this->installedLocales);
        $site->setSupportedLocales($this->installedLocales);
        $siteDao->insertSite($site);

        // Install email template list and data for each locale
        foreach ($this->installedLocales as $locale) {
            AppLocale::requireComponents(LOCALE_COMPONENT_APP_EMAIL, $locale);
        }
        $emailTemplateDao = DAORegistry::getDAO('EmailTemplateDAO'); /** @var EmailTemplateDAO $emailTemplateDao */
        $emailTemplateDao->installEmailTemplates($emailTemplateDao->getMainEmailTemplatesFilename(), $this->installedLocales);

        // Install default site settings
        $schemaService = Services::get('schema');
        $site = $schemaService->setDefaults(PKPSchemaService::SCHEMA_SITE, $site, $site->getSupportedLocales(), $site->getPrimaryLocale());
        $site->setData('contactEmail', $this->getParam('adminEmail'), $site->getPrimaryLocale());
        $siteDao->updateObject($site);

        return true;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\PKPInstall', '\PKPInstall');
}
