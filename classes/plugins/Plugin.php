<?php

/**
 * @defgroup plugins Plugins
 * Implements a plugin structure that can be used to flexibly extend PKP
 * software via the use of a set of plugin categories.
 */

/**
 * @file classes/plugins/Plugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Plugin
 *
 * @ingroup plugins
 *
 * @see PluginRegistry, PluginSettingsDAO
 *
 * @brief Abstract class for plugins
 *
 * For best performance, a plug-in should not be instantiated if it is
 * disabled or the current page/operation does not require the plug-in's
 * functionality.
 *
 * Newer plug-ins support enable/disable and request filter settings that
 * enable the PKP library plug-in framework to lazy-load plug-ins only
 * when their functionality is actually being required for a request.
 *
 * For backwards compatibility we need to assume that older plug-ins
 * do not support lazy-load because their register() method and hooks
 * may have side-effects required on all requests. We have no way of
 * knowing on which pages these side effects are important so we need
 * to load legacy plug-ins on all pages.
 *
 * In these cases the register() function will be called on every request
 * when the category the plug-in belongs to is being loaded. This was the
 * default behavior before plug-in lazy load was introduced.
 *
 * Plug-ins that want to enable lazy-load have to include a 'lazy-load'
 * setting in their version.xml:
 *
 *  <lazy-load>1</lazy-load>
 */

namespace PKP\plugins;

use APP\core\Application;
use PKP\plugins\ThemePlugin;
use APP\template\TemplateManager;
use Exception;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\facades\Repo;
use PKP\install\Installer;
use PKP\observers\events\PluginSettingChanged;
use PKP\site\Version;
use PKP\site\VersionDAO;
use PKP\template\PKPTemplateResource;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

// Define the well-known file name for filter configuration data.
define('PLUGIN_FILTER_DATAFILE', 'filterConfig.xml');

abstract class Plugin
{
    /** @var string Path name to files for this plugin */
    public $pluginPath;

    /** @var string Category name this plugin is registered to*/
    public $pluginCategory;

    /** @var PKPRequest the current request object */
    public $request;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /*
     * Public Plugin API (Registration and Initialization)
     */
    /**
     * Load and initialize the plug-in and register plugin hooks.
     *
     * For backwards compatibility this method will be called whenever
     * the plug-in's category is being loaded. If, however, registerOn()
     * returns an array then this method will only be called when
     * the plug-in is enabled and an entry in the result set of
     * registerOn() matches the current request operation. An empty array
     * matches all request operations.
     *
     * @param string $category Name of category plugin was registered to
     * @param string $path The path the plugin was found in
     * @param ?int $mainContextId To identify if the plugin is enabled
     *  we need a context. This context is usually taken from the
     *  request but sometimes there is no context in the request
     *  (e.g. when executing CLI commands). Then the main context
     *  can be given as an explicit ID.
     *
     * @return bool True iff plugin registered successfully; if false,
     * 	the plugin will not be executed.
     */
    public function register($category, $path, $mainContextId = null)
    {
        $this->pluginPath = $path;
        $this->pluginCategory = $category;
        if ($this->getInstallMigration()) {
            Hook::add('Installer::postInstall', $this->updateSchema(...));
        }
        if ($this->getInstallSitePluginSettingsFile()) {
            Hook::add('Installer::postInstall', $this->installSiteSettings(...));
        }
        if ($this->getInstallEmailTemplatesFile()) {
            Hook::add('Installer::postInstall', $this->installEmailTemplates(...));
            Hook::add('Locale::installLocale', $this->installLocale(...));
        }
        if ($this->getContextSpecificPluginSettingsFile()) {
            Hook::add('Context::add', $this->installContextSpecificSettings(...));
        }

        Hook::add('Installer::postInstall', $this->installFilters(...));

        $this->_registerTemplateResource();
        return true;
    }

    /**
     * Protected methods (may be overridden by custom plugins)
     */

    //
    // Plugin Display
    //

    /**
     * Get the name of this plugin. The name must be unique within
     * its category, and should be suitable for part of a filename
     * (ie short, no spaces, and no dependencies on cases being unique).
     *
     * @return string name of plugin
     */
    abstract public function getName();

    /**
     * Get the display name for this plugin.
     *
     * @return string
     */
    abstract public function getDisplayName();

    /**
     * Get a description of this plugin.
     *
     * @return string
     */
    abstract public function getDescription();

    //
    // Plugin Behavior and Management
    //

    /**
     * Return a number indicating the sequence in which this plugin
     * should be registered compared to others of its category.
     * Higher = later.
     *
     * @return int
     */
    public function getSeq()
    {
        return 0;
    }

    /**
     * Site-wide plugins should override this function to return true.
     *
     * @return bool
     */
    public function isSitePlugin()
    {
        return false;
    }

    /**
     * Perform a management function.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage A JSON-encoded response
     */
    public function manage($args, $request)
    {
        throw new Exception('Unhandled management action!');
    }

    /**
     * Determine whether or not this plugin should be hidden from the
     * management interface. Useful in the case of derivative plugins,
     * i.e. when a generic plugin registers a feed plugin.
     *
     * @return bool
     */
    public function getHideManagement()
    {
        return false;
    }

    //
    // Plugin Installation
    //

    /**
     * @deprecated See https://github.com/pkp/pkp-lib/issues/2493
     */
    final public function getInstallSchemaFile()
    {
    }

    /**
     * Get the installation migration for this plugin.
     *
     * @return \Illuminate\Database\Migrations\Migration|\PKP\migration\Migration|null
     */
    public function getInstallMigration()
    {
        return null;
    }

    /**
     * Get the filename of the settings data for this plugin to install
     * when the system is installed (i.e. site-level plugin settings).
     * Subclasses using default settings should override this.
     *
     * @return string
     */
    public function getInstallSitePluginSettingsFile()
    {
        return null;
    }

    /**
     * Get the filename of the settings data for this plugin to install
     * when a new application context (e.g. journal, conference or press)
     * is installed.
     *
     * Subclasses using default settings should override this.
     *
     * @return string
     */
    public function getContextSpecificPluginSettingsFile()
    {
        return null;
    }

    /**
     * Get the filename of the email templates for this plugin.
     * Subclasses using email templates should override this.
     *
     * @return string
     */
    public function getInstallEmailTemplatesFile()
    {
        return null;
    }

    /**
     * @deprecated Starting with OJS/OMP 3.2, localized content should be specified via getInstallEmailTemplatesFile(). (pkp/pkp-lib#5461)
     */
    final public function getInstallEmailTemplateDataFile()
    {
        throw new Exception('Use getInstallEmailTemplatesFile instead of deprecated getInstallEmailTemplateDataFile. See: https://docs.pkp.sfu.ca/dev/release-notebooks/en/3.2-release-notebook#email-templates-now-use-po-files');
    }

    /**
     * Get the filename(s) of the filter configuration data for
     * this plugin. Subclasses using filters can override this.
     *
     * The default implementation establishes "well known" locations
     * for the filter configuration. If you keep your files in these
     * locations then there's no need to override this method.
     *
     * @return string|array one or more file locations.
     */
    public function getInstallFilterConfigFiles()
    {
        // Construct the well-known filter configuration file names.
        $filterConfigFile = $this->getPluginPath() . '/filter/' . PLUGIN_FILTER_DATAFILE;
        $filterConfigFiles = [
            './lib/pkp/' . $filterConfigFile,
            './' . $filterConfigFile
        ];
        return $filterConfigFiles;
    }

    /*
     * Protected helper methods (can be used by custom plugins but
     * should not be overridden by custom plugins)
     */
    /**
     * Get the name of the category this plugin is registered to.
     *
     * @return string category
     */
    public function getCategory()
    {
        return $this->pluginCategory;
    }

    /**
     * Get the path this plugin's files are located in.
     *
     * @return string pathname
     */
    public function getPluginPath()
    {
        return $this->pluginPath;
    }

    /**
     * Get the directory name of the plugin
     *
     * @return string directory name
     */
    public function getDirName()
    {
        return basename($this->pluginPath);
    }

    /**
     * Return the Laravel view path for a template in this plugin.
     *
     * When a template is specified, returns a Laravel view namespace (e.g., "funding::listFunders").
     * Laravel's view system resolves the actual file (Blade or Smarty) automatically.
     *
     * When no template is specified, returns just the view namespace (e.g., "funding").
     *
     * @param string $template path/filename, if desired
     *
     * @return string
     */
    public function getTemplateResource($template = null)
    {
        $namespace = $this->getTemplateViewNamespace();

        if ($template === null) {
            return $namespace;
        }

        // Remove .tpl extension and convert slashes to dots
        $viewPath = preg_replace('/\.tpl$/', '', $template);
        $viewPath = str_replace(['/', '\\'], '.', $viewPath);

        return "{$namespace}::{$viewPath}";
    }

    /**
     * Return the canonical template path of this plug-in
     *
     * @param bool $inCore Return the core template path if true.
     *
     * @return string|null
     */
    public function getTemplatePath($inCore = false)
    {
        $templatePath = ($inCore ? PKP_LIB_PATH . '/' : '') . "{$this->getPluginPath()}/templates";
        if (is_dir($templatePath)) {
            return $templatePath;
        }
        return null;
    }

    /**
     * Get the view namespace of this plugin's templates for blade views and components
     * 
     * Default it set to the plugin name but
     * plugin can override this method to return a different view namespace
     */
    public function getTemplateViewNamespace(): string
    {
        return $this->getName();
    }

    /**
     * Get the blade view component class namespace of this plugin's
     * 
     * Default it set to the `PLUGIN_NAMESPACE\classes\components`
     * plugin can override this method to return a different view namespace
     */
    public function getComponentClassNamespace(): string
    {
        $className = get_class($this);

        $namespace = substr($className, 0, strrpos($className, '\\'));

        return $namespace . '\\classes\\components';
    }

    /**
     * Register this plugin's templates as a template resource
     *
     * @param bool $inCore True iff this is a core resource.
     */
    protected function _registerTemplateResource($inCore = false)
    {
        if ($templatePath = $this->getTemplatePath($inCore)) {
            // Register the plugin's template path to render blade views and components
            $fileViewFinder = app()->get('view.finder'); /** @var \Illuminate\View\FileViewFinder $fileViewFinder */
            $fileViewFinder->addLocation(app()->basePath($templatePath));

            // Auto register the plugin's view namespace and component class namespace
            $this->_registerTemplateViewNamespace($this->getTemplateViewNamespace(), $inCore);
            $this->_registerViewComponentNamespace($this->getComponentClassNamespace());

            // Register as Smarty resource so {include file="pluginname::template"} works at compile time
            $templateMgr = \APP\template\TemplateManager::getManager();
            $templateMgr->registerResource(
                $this->getTemplateViewNamespace(),
                new PKPTemplateResource($templatePath)
            );
        }
    }

    /**
     * Register the blade view namespaces for this plugin
     */
    protected function _registerTemplateViewNamespace(string $viewNamespace, bool $inCore = false): void
    {
        $templatePath = $this->getTemplatePath($inCore);
        
        if (!$templatePath) {
            throw new \Exception('unable to resolve the template path');
        }

        // Set or replace(if already set) the plugin's view namespace same as plugin name
        view()->replaceNamespace($viewNamespace, app()->basePath($templatePath));
            
        // Make sure to set the view namespace always available for plugins view
        View::composer(
            "{$viewNamespace}::*",
            fn (\Illuminate\View\View $view) => $view->with('viewNamespace', $viewNamespace)
        );
    }

    /**
     * Register the blade component class namespaces for this plugin
     * Alternatively, we can use the `Blade::componentNamespace` method to register the component class namespaces
     */
    protected function _registerViewComponentNamespace(string $componentNamespacePath): void
    {
        Blade::componentNamespace($componentNamespacePath, $this->getTemplateViewNamespace());
    }

    /**
     * Template override hook handler
     *
     * Call this method when an enabled plugin is registered in order to override
     * template files. Any plugin which calls this method can override template files.
     *
     * Handles two view name formats:
     * - Core templates (dot notation): "frontend.pages.article"
     *   Override at: templates/frontend/pages/article.blade (or .tpl)
     * - Plugin templates (namespaced): "funding::listFunders"
     *   Override at: templates/plugins/generic/funding/templates/listFunders.blade (or .tpl)
     *
     * @param string $hookName View::resolveName
     * @param array $args [$viewName, &$overrideViewName]
     *
     * @return int Hook::CONTINUE
     */
    public function _overridePluginTemplates($hookName, $args)
    {
        $viewName = $args[0];
        $overrideViewName = &$args[1];

        // Convert view name to relative path for override check
        $checkPath = $this->_viewNameToOverridePath($viewName);
        if ($checkPath === null) {
            return Hook::CONTINUE;
        }

        // Check if an overriding template exists
        if ($overriddenView = $this->_findOverriddenTemplate($checkPath)) {
            $overrideViewName = $overriddenView;
        }

        return Hook::CONTINUE;
    }

    /**
     * Convert a view name to a relative path for override checking
     *
     * Examples:
     *   "frontend.pages.article"     → "templates/frontend/pages/article"
     *   "funding::listFunders"       → "templates/plugins/generic/funding/templates/listFunders"
     *   "app::frontend.pages.article" → null (explicit namespace = skip)
     *   "pkp::common.header"         → null (explicit namespace = skip)
     *
     * Note: Smarty's app: prefix is stripped by smartyPathToViewName() before reaching here,
     * so "app:template.tpl" becomes just "template" (no namespace) and allows override.
     * Only explicit Laravel namespace syntax (app::, pkp::) skips override.
     *
     * @param string $viewName View name (dot notation or namespaced)
     * @return string|null Relative path from plugin root, or null to skip
     */
    protected function _viewNameToOverridePath(string $viewName): ?string
    {
        if (str_contains($viewName, '::')) {
            [$namespace, $templateName] = explode('::', $viewName, 2);

            // Skip app:: and pkp:: - explicit core namespaces shouldn't be overridden
            // (Smarty's app: prefix is already stripped before reaching here)
            if ($namespace === 'pkp' || $namespace === 'app') {
                return null;
            }

            // For plugin namespaces, build full override path structure
            $hints = app('view.finder')->getHints();
            if (!isset($hints[$namespace][0])) {
                return null;
            }

            // Convert absolute plugin path to relative
            $pluginTemplatePath = $hints[$namespace][0];
            $basePath = app()->basePath();
            if (str_starts_with($pluginTemplatePath, $basePath)) {
                $pluginTemplatePath = substr($pluginTemplatePath, strlen($basePath) + 1);
            }

            return 'templates/' . $pluginTemplatePath . '/' . str_replace('.', '/', $templateName);
        }

        // Dot notation views
        return 'templates/' . str_replace('.', '/', $viewName);
    }

    /**
     * Recursive check for overriding templates
     *
     * @param string $path Relative path from plugin root (e.g., "templates/frontend/pages/article")
     * @return string|null View name if override found, null otherwise
     */
    protected function _findOverriddenTemplate(string $path): ?string
    {
        $fullPath = $this->getPluginPath() . '/' . $path;

        // Check for Blade override
        if (file_exists($fullPath . '.blade')) {
            return $this->_pathToViewName($path);
        }

        // Check for Smarty override
        if (file_exists($fullPath . '.tpl')) {
            return $this->_pathToViewName($path);
        }

        // Recursive check for parent themes
        if ($this instanceof ThemePlugin && $this->parent) {
            return $this->parent->_findOverriddenTemplate($path);
        }

        return null;
    }

    /**
     * Convert a template path back to a namespaced view name
     *
     * @param string $path Path like "templates/frontend/pages/article"
     * @return string View name like "themename::frontend.pages.article"
     */
    protected function _pathToViewName(string $path): string
    {
        // Remove "templates/" prefix and convert slashes to dots
        $viewPath = preg_replace('#^templates/#', '', $path);
        $viewPath = str_replace('/', '.', $viewPath);
        return $this->getName() . '::' . $viewPath;
    }

    /**
     * Load locale data for this plugin.
     */
    public function addLocaleData(): void
    {
        $basePath = $this->getPluginPath() . '/locale';
        foreach ([$basePath, "lib/pkp/{$basePath}"] as $path) {
            if (is_dir($path)) {
                Locale::registerPath($path);
            }
        }
    }

    /**
     * Contains and return the list of plugin setting name which should be encrypted/decrypted
     * at the time to DB store/retrive.
     */
    public function getEncryptedSettingFields(): array
    {
        return [];
    }

    /**
     * Retrieve a plugin setting within the given context
     *
     * @param ?int $contextId Context ID
     * @param string $name Setting name
     */
    public function getSetting($contextId, $name)
    {
        if (!Application::isUpgrading() && !Application::isInstalled()) {
            return null;
        }

        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $value = $pluginSettingsDao->getSetting($contextId, $this->getName(), $name);

        if (in_array($name, $this->getEncryptedSettingFields()) && !empty($value) && !is_null($value)) {
            return app()->decrypt($value);
        }

        return $value;
    }

    /**
     * Update a plugin setting within the given context.
     *
     * @param ?int $contextId Context ID
     * @param string $name The name of the setting
     * @param mixed $value Setting value
     * @param string $type optional
     */
    public function updateSetting($contextId, $name, $value, $type = null)
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */

        if (in_array($name, $this->getEncryptedSettingFields()) && !empty($value) && !is_null($value)) {
            $value = app()->encrypt($value);
        }

        $pluginSettingsDao->updateSetting($contextId, $this->getName(), $name, $value, $type);

        event(new PluginSettingChanged($this, $name, $value, $contextId));
    }

    /*
     * Helper methods (for internal use only, should not
     * be used by custom plug-ins)
     *
     * NB: These methods may change without notice in the future!
     */
    /**
     * Callback used to install settings on system install.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function installSiteSettings($hookName, $args)
    {
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $pluginSettingsDao->installSettings(PKPApplication::SITE_CONTEXT_ID, $this->getName(), $this->getInstallSitePluginSettingsFile());

        return false;
    }

    /**
     * Callback used to install settings on new context
     * (e.g. journal, conference or press) creation.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function installContextSpecificSettings($hookName, $args)
    {
        $context = $args[0];
        $pluginSettingsDao = DAORegistry::getDAO('PluginSettingsDAO'); /** @var PluginSettingsDAO $pluginSettingsDao */
        $pluginSettingsDao->installSettings($context->getId(), $this->getName(), $this->getContextSpecificPluginSettingsFile());
        return false;
    }

    /**
     * Callback used to install email templates.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function installEmailTemplates($hookName, $args)
    {
        $installer = &$args[0]; /** @var Installer $installer */
        $result = &$args[1];

        // Load email template data as required from the locale files.
        $locales = [];
        foreach ($installer->installedLocales as $locale) {
            if (file_exists($this->getPluginPath() . "/locale/{$locale}/emails.po")) {
                $locales[] = $locale;
            }
        }
        // Localized data is needed by the email installation
        $this->addLocaleData();
        $status = Repo::emailTemplate()->dao->installEmailTemplates($this->getInstallEmailTemplatesFile(), $locales, null, true, true);

        if ($status === false) {
            // The template file seems to be invalid.
            $installer->setError(Installer::INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallEmailTemplatesFile(), __('installer.installParseEmailTemplatesFileError')));
            $result = false;
        }
        return false;
    }

    /**
     * Callback used to install email template data on locale install.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function installLocale($hookName, $args)
    {
        $locale = &$args[0];
        if (file_exists($this->getPluginPath() . "/locale/{$locale}/emails.po")) {
            $this->addLocaleData();
            Repo::emailTemplate()->dao->installEmailTemplateLocaleData($this->getInstallEmailTemplatesFile(), [$locale]);
        }
        return false;
    }

    /**
     * Callback used to install filters.
     *
     * @param string $hookName
     * @param array $args
     */
    public function installFilters($hookName, $args)
    {
        $installer = &$args[0]; /** @var Installer $installer */
        $result = &$args[1]; /** @var bool $result */

        // Get the filter configuration file name(s).
        $filterConfigFiles = $this->getInstallFilterConfigFiles();
        if (is_scalar($filterConfigFiles)) {
            $filterConfigFiles = [$filterConfigFiles];
        }

        // Run through the config file positions and see
        // whether one of these exists and needs to be installed.
        foreach ($filterConfigFiles as $filterConfigFile) {
            // Is there a filter configuration?
            if (!file_exists($filterConfigFile)) {
                continue;
            }

            // Install the filter configuration.
            $result = $installer->installFilterConfig($filterConfigFile);
            if (!$result) {
                // The filter configuration file seems to be invalid.
                $installer->setError(Installer::INSTALLER_ERROR_DB, str_replace('{$file}', $filterConfigFile, __('installer.installParseFilterConfigFileError')));
            }
        }

        // Do not stop installation.
        return false;
    }

    /**
     * Called during the install process to install the plugin schema,
     * if applicable.
     *
     * @param string $hookName
     * @param array $args
     *
     * @return bool
     */
    public function updateSchema($hookName, $args)
    {
        $installer = &$args[0];
        $result = &$args[1];

        if ($migration = $this->getInstallMigration()) {
            try {
                $migration->up();
            } catch (Exception $e) {
                $installer->setError(Installer::INSTALLER_ERROR_DB, __('installer.installMigrationError', ['class' => get_class($migration), 'message' => $e->getMessage()]));
                $result = false;
            }
        }
        return false;
    }

    /**
     * Extend the {url ...} smarty to support plugins.
     *
     * @param array $params
     * @param \Smarty $smarty
     *
     * @return string
     */
    public function smartyPluginUrl($params, $smarty)
    {
        $path = [$this->getCategory(), $this->getName()];
        if (is_array($params['path'])) {
            $params['path'] = array_merge($path, $params['path']);
        } elseif (!empty($params['path'])) {
            $params['path'] = array_merge($path, [$params['path']]);
        } else {
            $params['path'] = $path;
        }
        return $smarty->smartyUrl($params, $smarty);
    }

    /**
     * Get the current version of this plugin
     *
     * @return ?Version
     */
    public function getCurrentVersion()
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $pluginPath = $this->getPluginPath();
        $product = basename($pluginPath);
        $category = basename(dirname($pluginPath));
        $installedPlugin = $versionDao->getCurrentVersion('plugins.' . $category, $product);

        if ($installedPlugin) {
            return $installedPlugin;
        } else {
            return false;
        }
    }

    /**
     * Get the current request object
     *
     * @return PKPRequest
     */
    public function &getRequest()
    {
        if (!$this->request) {
            $this->request = &Registry::get('request');
        }
        return $this->request;
    }

    /*
     * Private helper methods
     */
    /**
     * Get a list of link actions for plugin management.
     *
     * @param PKPRequest $request
     * @param array $actionArgs The list of action args to be included in request URLs.
     *
     * @return array List of LinkActions
     */
    public function getActions($request, $actionArgs)
    {
        return [];
    }

    /**
     * Determine whether the plugin can be enabled.
     *
     * @return bool
     */
    public function getCanEnable()
    {
        return false;
    }

    /**
     * Determine whether the plugin can be disabled.
     *
     * @return bool
     */
    public function getCanDisable()
    {
        return false;
    }

    /**
     * Determine whether the plugin is enabled.
     *
     * @return bool
     */
    public function getEnabled()
    {
        return true;
    }
}
