<?php

/**
 * @file classes/install/Installer.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Installer
 *
 * @ingroup install
 *
 * @brief Base class for install and upgrade scripts.
 */

namespace PKP\install;

use APP\core\Application;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPContainer;
use PKP\db\DAORegistry;
use PKP\db\DBDataXMLParser;
use PKP\db\XMLDAO;
use PKP\facades\Locale;
use PKP\file\FileManager;
use PKP\filter\FilterHelper;
use PKP\navigationMenu\NavigationMenuDAO;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\site\SiteDAO;
use PKP\site\Version;
use PKP\site\VersionCheck;
use PKP\site\VersionDAO;
use PKP\xml\PKPXMLParser;
use PKP\xml\XMLNode;

class Installer
{
    // Installer error codes
    public const INSTALLER_ERROR_GENERAL = 1;
    public const INSTALLER_ERROR_DB = 2;

    public const INSTALLER_DATA_DIR = 'dbscripts/xml';
    public const INSTALLER_DEFAULT_LOCALE = 'en';

    /** @var string descriptor path (relative to INSTALLER_DATA_DIR) */
    public $descriptor;

    /** @var bool indicates if a plugin is being installed (thus modifying the descriptor path) */
    public $isPlugin;

    /** @var array installation parameters */
    public $params;

    /** @var Version currently installed version */
    public $currentVersion;

    /** @var Version version after installation */
    public $newVersion;

    /** @var string default locale */
    public $locale;

    /** @var array available locales */
    public $installedLocales;

    /** @var DBDataXMLParser database data parser */
    public $dataXMLParser;

    /** @var array installer actions to be performed */
    public $actions;

    /** @var array SQL statements for database installation */
    public $sql;

    /** @var array installation notes */
    public $notes;

    /** @var string contents of the updated config file */
    public $configContents;

    /** @var bool indicating if config file was written or not */
    public $wroteConfig;

    /** @var int error code (null | INSTALLER_ERROR_GENERAL | INSTALLER_ERROR_DB) */
    public $errorType;

    /** @var string the error message, if an installation error has occurred */
    public $errorMsg;

    /** @var object logging object */
    public $logger;

    /** @var array List of migrations executed already */
    public $migrations = [];

    /** @var array List of email template variables to rename. App-specific */
    protected $appEmailTemplateVariableNames = [];

    /**
     * Constructor.
     *
     * @param string $descriptor descriptor path
     * @param array $params installer parameters
     * @param bool $isPlugin true iff a plugin is being installed
     *
     * @hook Installer::Installer [[$this, &$descriptor, &$params]]
     */
    public function __construct($descriptor, $params = [], $isPlugin = false)
    {
        // Load all plugins. If any of them use installer hooks,
        // they'll need to be loaded here.
        PluginRegistry::loadAllPlugins();
        $this->isPlugin = $isPlugin;

        // Give the Hook registry the opportunity to override this
        // method or alter its parameters.
        if (!Hook::call('Installer::Installer', [$this, &$descriptor, &$params])) {
            $this->descriptor = $descriptor;
            $this->params = $params;
            $this->actions = [];
            $this->sql = [];
            $this->notes = [];
            $this->wroteConfig = true;
        }
    }

    /**
     * Returns true iff this is an upgrade process.
     */
    public function isUpgrade()
    {
        exit('ABSTRACT CLASS');
    }

    /**
     * Destroy / clean-up after the installer.
     *
     * @hook Installer::destroy [[$this]]
     */
    public function destroy()
    {
        Hook::call('Installer::destroy', [$this]);
    }

    /**
     * Pre-installation.
     *
     * @return bool
     *
     * @hook Installer::preInstall [[$this, &$result]]
     */
    public function preInstall()
    {
        $this->log('pre-install');
        if (!isset($this->currentVersion)) {
            // Retrieve the currently installed version
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            $this->currentVersion = $versionDao->getCurrentVersion();
        }

        if (!isset($this->locale)) {
            $this->locale = Locale::getLocale();
        }

        if (!isset($this->installedLocales)) {
            $this->installedLocales = array_keys(Locale::getLocales());
        }

        if (!isset($this->dataXMLParser)) {
            $this->dataXMLParser = new DBDataXMLParser();
        }

        $result = true;
        Hook::call('Installer::preInstall', [$this, &$result]);

        return $result;
    }

    /**
     * Installation.
     *
     * @return bool
     */
    public function execute()
    {
        // Ensure that the installation will not get interrupted if it takes
        // longer than max_execution_time (php.ini). Note that this does not
        // work under safe mode.
        @set_time_limit(0);

        if (!$this->preInstall()) {
            return false;
        }

        if (!$this->parseInstaller()) {
            return false;
        }

        if (!$this->executeInstaller()) {
            return false;
        }

        if (!$this->postInstall()) {
            return false;
        }

        return $this->updateVersion();
    }

    /**
     * Post-installation.
     *
     * @return bool
     *
     * @hook Installer::postInstall [[$this, &$result]]
     */
    public function postInstall()
    {
        $this->log('post-install');
        $result = true;
        Hook::call('Installer::postInstall', [$this, &$result]);
        return $result;
    }


    /**
     * Record message to installation log.
     *
     * @param string $message
     */
    public function log($message)
    {
        if (isset($this->logger)) {
            call_user_func([$this->logger, 'log'], $message);
        }
    }


    //
    // Main actions
    //

    /**
     * Parse the installation descriptor XML file.
     *
     * @return bool
     *
     * @hook Installer::parseInstaller [[$this, &$result]]
     */
    public function parseInstaller()
    {
        // Read installation descriptor file
        $this->log(sprintf('load: %s', $this->descriptor));
        $xmlParser = new PKPXMLParser();
        $installPath = $this->isPlugin ? $this->descriptor : self::INSTALLER_DATA_DIR . "/{$this->descriptor}";
        $installTree = $xmlParser->parse($installPath);
        if (!$installTree) {
            // Error reading installation file
            $this->setError(self::INSTALLER_ERROR_GENERAL, 'installer.installFileError');
            return false;
        }

        $versionString = $installTree->getAttribute('version');
        if (isset($versionString)) {
            $this->newVersion = Version::fromString($versionString);
        } else {
            $this->newVersion = $this->currentVersion;
        }

        // Parse descriptor
        $this->parseInstallNodes($installTree);

        $result = $this->getErrorType() == 0;

        Hook::call('Installer::parseInstaller', [$this, &$result]);
        return $result;
    }

    /**
     * Execute the installer actions.
     *
     * @return bool
     *
     * @hook Installer::executeInstaller [[$this, &$result]]
     */
    public function executeInstaller()
    {
        $this->log(sprintf('version: %s', $this->newVersion->getVersionString(false)));
        foreach ($this->actions as $action) {
            if (!$this->executeAction($action)) {
                return false;
            }
        }

        $result = true;
        Hook::call('Installer::executeInstaller', [$this, &$result]);

        return $result;
    }

    /**
     * Update the version number.
     *
     * @return bool
     *
     * @hook Installer::updateVersion [[$this, &$result]]
     */
    public function updateVersion()
    {
        if ($this->newVersion->compare($this->currentVersion) > 0) {
            $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
            if (!$versionDao->insertVersion($this->newVersion)) {
                return false;
            }
        }

        $result = true;
        Hook::call('Installer::updateVersion', [$this, &$result]);

        return $result;
    }


    //
    // Installer Parsing
    //

    /**
     * Parse children nodes in the install descriptor.
     *
     * @param XMLNode $installTree
     */
    public function parseInstallNodes($installTree)
    {
        foreach ($installTree->getChildren() as $node) {
            switch ($node->getName()) {
                case 'schema':
                case 'data':
                case 'code':
                case 'migration':
                case 'note':
                    $this->addInstallAction($node);
                    break;
                case 'upgrade':
                    $minVersion = $node->getAttribute('minversion');
                    $maxVersion = $node->getAttribute('maxversion');
                    if ((!isset($minVersion) || $this->currentVersion->compare($minVersion) >= 0) && (!isset($maxVersion) || $this->currentVersion->compare($maxVersion) <= 0)) {
                        $this->parseInstallNodes($node);
                    }
                    break;
            }
        }
    }

    /**
     * Add an installer action from the descriptor.
     *
     * @param XMLNode $node
     */
    public function addInstallAction($node)
    {
        $fileName = $node->getAttribute('file');

        if (!isset($fileName)) {
            $this->actions[] = ['type' => $node->getName(), 'file' => null, 'attr' => $node->getAttributes()];
        } elseif (strstr($fileName, '{$installedLocale}')) {
            // Filename substitution for locales
            foreach ($this->installedLocales as $thisLocale) {
                $newFileName = str_replace('{$installedLocale}', $thisLocale, $fileName);
                $this->actions[] = ['type' => $node->getName(), 'file' => $newFileName, 'attr' => $node->getAttributes()];
            }
        } else {
            $newFileName = str_replace('{$locale}', $this->locale, $fileName);
            if (!file_exists($newFileName)) {
                // Use version from default locale if data file is not available in the selected locale
                $newFileName = str_replace('{$locale}', self::INSTALLER_DEFAULT_LOCALE, $fileName);
            }

            $this->actions[] = ['type' => $node->getName(), 'file' => $newFileName, 'attr' => $node->getAttributes()];
        }
    }


    //
    // Installer Execution
    //

    /**
     * Execute a single installer action.
     *
     * @param array $action
     *
     * @return bool
     */
    public function executeAction($action)
    {
        switch ($action['type']) {
            case 'data':
                $fileName = $action['file'];
                $condition = $action['attr']['condition'] ?? null;
                $includeAction = true;
                if ($condition) {
                    // Create a new scope to evaluate the condition
                    $evalFunction = function ($installer, $action) use ($condition) {
                        return eval($condition);
                    };
                    $includeAction = $evalFunction($this, $action);
                }
                $this->log('data: ' . $action['file'] . ($includeAction ? '' : ' (skipped)'));
                if (!$includeAction) {
                    break;
                }

                $sql = $this->dataXMLParser->parseData($fileName);
                // We might get an empty SQL if the upgrade script has
                // been executed before.
                if ($sql) {
                    return $this->executeSQL($sql);
                }
                break;
            case 'migration':
                assert(isset($action['attr']['class']));
                $fullClassName = $action['attr']['class'];
                if (strpos($fullClassName, '\\') !== false) {
                    // Migration is specified fully-qualified PHP class name; allow autoloading
                    $this->log(sprintf('migration: %s', $fullClassName));
                    $migration = new $fullClassName($this, $action['attr']);
                } else {
                    // Migration is specified using old-style class.name.like.this
                    // This behaviour is DEPRECATED as of 3.4.0
                    import($fullClassName);
                    $shortClassName = substr($fullClassName, strrpos($fullClassName, '.') + 1);
                    $this->log(sprintf('migration: %s', $shortClassName));
                    $migration = new $shortClassName($this, $action['attr']);
                }
                try {
                    $migration->up();
                    $this->migrations[] = $migration;
                } catch (Exception $e) {
                    // Log an error message
                    $this->setError(
                        self::INSTALLER_ERROR_DB,
                        Config::getVar('debug', 'show_stacktrace') ? (string) $e : $e->getMessage()
                    );

                    // Back out already-executed migrations.
                    while ($previousMigration = array_pop($this->migrations)) {
                        try {
                            $this->log(sprintf('revert migration: %s', get_class($previousMigration)));
                            $previousMigration->down();
                        } catch (DowngradeNotSupportedException $e) {
                            $this->log(sprintf('downgrade for "%s" unsupported: %s', get_class($previousMigration), $e->getMessage()));
                            break;
                        } catch (Exception $e) {
                            $this->log(sprintf('error while downgrading "%s": %s', get_class($previousMigration), Config::getVar('debug', 'show_stacktrace') ? (string) $e : $e->getMessage()));
                            break;
                        }
                    }
                    return false;
                }
                return true;
            case 'code':
                $condition = $action['attr']['condition'] ?? null;
                $includeAction = true;
                if ($condition) {
                    // Create a new scope to evaluate the condition
                    $evalFunction = function ($installer, $action) use ($condition) {
                        return eval($condition);
                    };
                    $includeAction = $evalFunction($this, $action);
                }
                $this->log(sprintf('code: %s %s::%s' . ($includeAction ? '' : ' (skipped)'), $action['file'] ?? 'Installer', $action['attr']['class'] ?? 'Installer', $action['attr']['function']));
                if (!$includeAction) {
                    return true;
                } // Condition not met; skip the action.

                if (isset($action['file'])) {
                    require_once($action['file']);
                }
                if (isset($action['attr']['class'])) {
                    return call_user_func([$action['attr']['class'], $action['attr']['function']], $this, $action['attr']);
                }
                return call_user_func([$this, $action['attr']['function']], $this, $action['attr']);
            case 'note':
                $this->log(sprintf('note: %s', $action['file']));
                $this->notes[] = join('', file($action['file']));
                break;
            default: throw new Exception("Unknown action type {$action['type']}!");
        }

        return true;
    }

    /**
     * Execute an SQL statement.
     *
     *
     * @return bool
     */
    public function executeSQL($sql)
    {
        if (is_array($sql)) {
            foreach ($sql as $stmt) {
                if (!$this->executeSQL($stmt)) {
                    return false;
                }
            }
        } else {
            try {
                DB::affectingStatement($sql);
            } catch (Exception $e) {
                $this->setError(self::INSTALLER_ERROR_DB, $e->getMessage());
                return false;
            }
        }

        return true;
    }

    /**
     * Update the specified configuration parameters.
     *
     * @param array $configParams
     *
     * @return bool
     */
    public function updateConfig($configParams)
    {
        // Update config file
        $configParser = new \PKP\config\ConfigParser();
        if (!$configParser->updateConfig(Config::getConfigFileName(), $configParams)) {
            // Error reading config file
            $this->setError(self::INSTALLER_ERROR_GENERAL, 'installer.configFileError');
            return false;
        }

        $this->configContents = $configParser->getFileContents();
        if (!$configParser->writeConfig(Config::getConfigFileName())) {
            $this->wroteConfig = false;
        }

        return true;
    }


    //
    // Accessors
    //

    /**
     * Get the value of an installation parameter.
     *
     * @param string $name
     */
    public function getParam($name)
    {
        return $this->params[$name] ?? null;
    }

    /**
     * Return currently installed version.
     *
     * @return Version
     */
    public function getCurrentVersion()
    {
        return $this->currentVersion;
    }

    /**
     * Return new version after installation.
     *
     * @return Version
     */
    public function getNewVersion()
    {
        return $this->newVersion;
    }

    /**
     * Get the set of SQL statements required to perform the install.
     *
     * @return array
     */
    public function getSQL()
    {
        return $this->sql;
    }

    /**
     * Get the set of installation notes.
     *
     * @return array
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Get the contents of the updated configuration file.
     *
     * @return string
     */
    public function getConfigContents()
    {
        return $this->configContents;
    }

    /**
     * Check if installer was able to write out new config file.
     *
     * @return bool
     */
    public function wroteConfig()
    {
        return $this->wroteConfig;
    }

    /**
     * Return the error code.
     * Valid return values are:
     *   - 0 = no error
     *   - INSTALLER_ERROR_GENERAL = general installation error.
     *   - INSTALLER_ERROR_DB = database installation error
     *
     * @return int
     */
    public function getErrorType()
    {
        return $this->errorType ?? 0;
    }

    /**
     * The error message, if an error has occurred.
     * In the case of a database error, an unlocalized string containing the error message is returned.
     * For any other error, a localization key for the error message is returned.
     *
     * @return string
     */
    public function getErrorMsg()
    {
        return $this->errorMsg;
    }

    /**
     * Return the error message as a localized string.
     *
     * @return string.
     */
    public function getErrorString()
    {
        switch ($this->getErrorType()) {
            case self::INSTALLER_ERROR_DB:
                return 'DB: ' . $this->getErrorMsg();
            default:
                return __($this->getErrorMsg());
        }
    }

    /**
     * Set the error type and messgae.
     *
     * @param int $type
     * @param string $msg Text message (INSTALLER_ERROR_DB) or locale key (otherwise)
     */
    public function setError($type, $msg)
    {
        $this->errorType = $type;
        $this->errorMsg = $msg;
    }

    /**
     * Set the logger for this installer.
     *
     * @param object $logger
     */
    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    /**
     * Clear the data cache files (needed because of direct tinkering
     * with settings tables)
     *
     * @return bool
     */
    public function clearDataCache()
    {
        // Clear Laravel caches
        $cacheManager = PKPContainer::getInstance()['cache'];
        $cacheManager->store()->flush();

        return true;
    }

    /**
     * Set the current version for this installer.
     *
     * @param Version $version
     */
    public function setCurrentVersion($version)
    {
        $this->currentVersion = $version;
    }

    /**
     * For upgrade: install email templates and data
     *
     * @deprecated 3.4 Do not use <code function="installEmailTemplate" ...>
     *   in upgrade scripts to 3.4 and later versions. This is no longer in
     *   sync with the PKP\emailTemplates\DAO methods to install email templates.
     *
     * @param object $installer
     * @param array $attr Attributes: array containing
     *  'key' => 'EMAIL_KEY_HERE',
     *  'locales' => 'en,fr_CA,...'
     */
    public function installEmailTemplate($installer, $attr)
    {
        $locales = explode(',', $attr['locales'] ?? '');
        $emailKey = $attr['key'] ?? '';

        if (!$emailKey) {
            throw new Exception('Tried to install email template but no template key provided.');
        }

        $xmlDao = new XMLDAO();
        $data = $xmlDao->parseStruct('registry/emailTemplates.xml', ['email']);
        if (!isset($data['email'])) {
            return false;
        }

        if (empty($locales)) {
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $site = $siteDao->getSite(); /** @var \PKP\site\Site $site */
            $locales = $site->getInstalledLocales();
        }

        // filter out any invalid locales that is not supported by site
        $allLocales = array_keys(Locale::getLocales());
        if (!empty($invalidLocales = array_diff($locales, $allLocales))) {
            $locales = array_diff($locales, $invalidLocales);
        }

        foreach ($data['email'] as $entry) {
            $attrs = $entry['attributes'];
            if ($emailKey && $emailKey != $attrs['key']) {
                continue;
            }
            if (DB::table('email_templates_default_data')->where('email_key', $attrs['key'])->exists()) {
                continue;
            }

            $subject = $attrs['subject'] ?? null;
            $body = $attrs['body'] ?? null;
            if ($subject && $body) {
                foreach ($locales as $locale) {
                    DB::table('email_templates_default_data')
                        ->where('email_key', $attrs['key'])
                        ->where('locale', $locale)
                        ->delete();

                    $previous = Locale::getMissingKeyHandler();
                    Locale::setMissingKeyHandler(fn (string $key): string => '');
                    $translatedSubject = __($subject, [], $locale);
                    $translatedBody = __($body, [], $locale);
                    Locale::setMissingKeyHandler($previous);
                    if ($translatedSubject !== null && $translatedBody !== null) {
                        DB::table('email_templates_default_data')->insert([
                            'email_key' => $attrs['key'],
                            'locale' => $locale,
                            'subject' => $this->renameEmailTemplateVariables($translatedSubject),
                            'body' => $this->renameEmailTemplateVariables($translatedBody),
                        ]);
                    }
                }
            }
        }

        return true;
    }

    /**
     * @deprecated 3.4
     * @see self::installEmailTemplate()
     */
    protected function renameEmailTemplateVariables($string): string
    {
        if (empty($this->appEmailTemplateVariableNames)) {
            return $string;
        }

        $variables = [];
        $replacements = [];
        foreach ($this->appEmailTemplateVariableNames as $key => $value) {
            $variables[] = '/\{\$' . $key . '\}/';
            $replacements[] = '{$' . $value . '}';
        }

        return preg_replace($variables, $replacements, $string);
    }

    /**
     * Install the given filter configuration file.
     *
     * @param string $filterConfigFile
     *
     * @return bool true when successful, otherwise false
     */
    public function installFilterConfig($filterConfigFile)
    {
        static $filterHelper = false;

        // Parse the filter configuration.
        $xmlParser = new PKPXMLParser();
        $tree = $xmlParser->parse($filterConfigFile);

        // Validate the filter configuration.
        if (!$tree) {
            return false;
        }

        // Get the filter helper.
        if ($filterHelper === false) {
            $filterHelper = new FilterHelper();
        }

        // Are there any filter groups to be installed?
        $filterGroupsNode = $tree->getChildByName('filterGroups');
        if ($filterGroupsNode instanceof XMLNode) {
            $filterHelper->installFilterGroups($filterGroupsNode);
        }

        // Are there any filters to be installed?
        $filtersNode = $tree->getChildByName('filters');
        if ($filtersNode instanceof XMLNode) {
            foreach ($filtersNode->getChildren() as $filterNode) { /** @var XMLNode $filterNode */
                $filterHelper->configureFilter($filterNode);
            }
        }

        return true;
    }

    /**
     * Insert or update plugin data in versions
     * and plugin_settings tables.
     *
     * @return bool
     */
    public function addPluginVersions()
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $fileManager = new FileManager();
        $categories = PluginRegistry::getCategories();
        foreach ($categories as $category) {
            PluginRegistry::loadCategory($category);
            $plugins = PluginRegistry::getPlugins($category);
            if (!empty($plugins)) {
                foreach ($plugins as $plugin) {
                    $versionFile = $plugin->getPluginPath() . '/version.xml';

                    if ($fileManager->fileExists($versionFile)) {
                        $versionInfo = VersionCheck::parseVersionXML($versionFile);
                        $pluginVersion = $versionInfo['version'];
                    } else {
                        $pluginVersion = new Version(
                            1,
                            0,
                            0,
                            0, // Major, minor, revision, build
                            Core::getCurrentDate(), // Date installed
                            1, // Current
                            'plugins.' . $category, // Type
                            basename($plugin->getPluginPath()), // Product
                            '', // Class name
                            0, // Lazy load
                            $plugin->isSitePlugin() // Site wide
                        );
                    }
                    $versionDao->insertVersion($pluginVersion, true);
                }
            }
        }

        return true;
    }

    /**
     * Fail the upgrade.
     *
     * @param Installer $installer
     * @param array $attr Attributes
     *
     * @return bool
     */
    public function abort($installer, $attr)
    {
        $installer->setError(self::INSTALLER_ERROR_GENERAL, $attr['message']);
        return false;
    }

    /**
     * For 3.1.0 upgrade.  DefaultMenus Defaults
     *
     * @return bool Success/failure
     */
    public function installDefaultNavigationMenus()
    {
        $contextDao = Application::getContextDAO();
        $navigationMenuDao = DAORegistry::getDAO('NavigationMenuDAO'); /** @var NavigationMenuDAO $navigationMenuDao */

        $contexts = $contextDao->getAll();
        while ($context = $contexts->next()) {
            $navigationMenuDao->installSettings($context->getId(), 'registry/navigationMenus.xml');
        }

        $navigationMenuDao->installSettings(PKPApplication::SITE_CONTEXT_ID, 'registry/navigationMenus.xml');

        return true;
    }

    /**
     * Check that the environment meets minimum PHP requirements.
     *
     * @return bool Success/failure
     */
    public function checkPhpVersion()
    {
        if (version_compare(PKPApplication::PHP_REQUIRED_VERSION, PHP_VERSION) != 1) {
            return true;
        }

        $this->setError(self::INSTALLER_ERROR_GENERAL, 'installer.unsupportedPhpError');
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\install\Installer', '\Installer');
    define('INSTALLER_ERROR_GENERAL', Installer::INSTALLER_ERROR_GENERAL);
    define('INSTALLER_ERROR_DB', Installer::INSTALLER_ERROR_DB);
    define('INSTALLER_DATA_DIR', Installer::INSTALLER_DATA_DIR);
    define('INSTALLER_DEFAULT_LOCALE', Installer::INSTALLER_DEFAULT_LOCALE);
}
