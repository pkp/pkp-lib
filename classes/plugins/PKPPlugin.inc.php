<?php

/**
 * @defgroup plugins
 */

/**
 * @file classes/plugins/PKPPlugin.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPlugin
 * @ingroup plugins
 * @see PluginRegistry, PluginSettingsDAO
 *
 * @brief Abstract class for plugins
 *
 * For best performance, a plug-in should only be instantiated when
 * it's functionality is actually required. This is the case if one
 * of the following plug-in events occurs:
 * - installation
 * - upgrade
 * - removal (uninstall)
 * - configuration (enabling/disabling the plug-in,
 *   changing plug-in settings)
 * - adding/removing locales
 * - registration (register hooks)
 * - initialization (per-request initialization tasks required
 *   by all hooks)
 * - hook call-back event (including calls to standard API
 *   methods of specialized plug-ins).
 *
 * Most importantly the plug-in should not be instantiated if it is
 * disabled and none of it's hooks or standardized API methods are being
 * called during a request.
 *
 * Newer plug-ins support settings and hook caching which enables the
 * PKP library plug-in framework to lazy-load plug-ins only when they
 * are enabled and their hooks are actually being called.
 *
 * For historic reasons we need to assume that older community plug-ins
 * do not support lazy-load because their register() method has other
 * side-effects than just hook registration. This means that we have to
 * call the register() method on every request even if the hooks registered
 * by the plug-in are not actually called. In these cases the register()
 * function will be called when (and only when) the plug-in is enabled and
 * the category the plug-in belongs to is being loaded. This was the
 * default behavior before plug-in lazy load was introduced.
 */

// $Id$


class PKPPlugin {
	/** @var $pluginPath String Path name to files for this plugin */
	var $pluginPath;

	/** @var $pluginCategory String Category name this plugin is registered to*/
	var $pluginCategory;

	/**
	 * Constructor
	 */
	function PKPPlugin() {
	}

	function getTemplatePath() {
		$basePath = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
		return "file:$basePath/" . $this->getPluginPath() . '/';
	}

	/**
	 * Get the path this plugin's files are located in.
	 * @return String pathname
	 */
	function getPluginPath() {
		return $this->pluginPath;
	}

	/**
	 * Get the name of the category this plugin is registered to.
	 * @return String category
	 */
	function getCategory() {
		return $this->pluginCategory;
	}

	/**
	 * Return a number indicating the sequence in which this plugin
	 * should be registered compared to others of its category.
	 * Higher = later.
	 */
	function getSeq() {
		return 0;
	}

	/**
	 * Whether hook caching is enabled.
	 *
	 * NB: For backwards compatibility only. New plug-ins
	 * should override this method and return true!
	 *
	 * You have to make sure that your register() method
	 * has no other side effects but registering hooks.
	 *
	 * All other plug-in functionality must be implemented
	 * via hooks.
	 */
	function getHookCachingEnabled() {
		return false;
	}

	/**
	 * Initialize plug-in
	 *
	 * Called just before the first plug-in hook or plug-in API call
	 * is being executed. If none of this plug-in's hooks or API methods
	 * are executed in a request then this method will not be called
	 * at all.
	 *
	 * Only use this method to provide infrastructure to the plug-in
	 * not to the application. Use application hooks to provide
	 * application-wide infrastructure.
	 *
	 * If you override this method then call the parent method first.
	 *
	 * @param $category String Name of category plugin was registered to
	 * @param $path String The path the plugin was found in
	 * @return boolean True iff plugin initialized successfully; if false,
	 * 	the plugin will not be executed.
	 */
	function initialize($category, $path) {
		$this->pluginPath = $path;
		$this->pluginCategory = $category;
		return true;
	}

	/**
	 * Register plugin hooks.
	 *
	 * For backwards compatibility this method will be called once per request
	 * when getHookCachingEnabled() returns false. Otherwise it will only be
	 * called when the hook cache needs refreshing. New plug-ins should only register
	 * hooks in the register() method and do all per-request initialization in
	 * the initialize() method.
	 *
	 * Whenever plug-ins provide infrastructure that needs to be made available to
	 * all requests then this should be done via hooks not in the register() method.
	 *
	 * @param $category String Name of category plugin was registered to
	 * @param $path String The path the plugin was found in
	 * @return boolean True iff plugin registered successfully; if false,
	 * 	the plugin will not be executed.
	 */
	function register($category, $path) {
		if ($this->getInstallSchemaFile()) {
			HookRegistry::register ('Installer::postInstall', array(&$this, 'updateSchema'));
		}
		if ($this->getInstallSitePluginSettingsFile()) {
			HookRegistry::register ('Installer::postInstall', array(&$this, 'installSiteSettings'));
		}
		if ($this->getInstallEmailTemplatesFile()) {
			HookRegistry::register ('Installer::postInstall', array(&$this, 'installEmailTemplates'));
		}
		if ($this->getInstallEmailTemplateDataFile()) {
			HookRegistry::register ('Installer::postInstall', array(&$this, 'installEmailTemplateData'));
			HookRegistry::register ('PKPLocale::installLocale', array(&$this, 'installLocale'));
		}
		if ($this->getInstallDataFile()) {
			HookRegistry::register ('Installer::postInstall', array(&$this, 'installData'));
		}
		if ($this->getContextSpecificPluginSettingsFile()) {
			HookRegistry::register ($this->_getContextSpecificInstallationHook(), array(&$this, 'installContextSpecificSettings'));
		}
		return true;
	}

	/**
	 * The application specific context installation hook.
	 * @return string
	 */
	function _getContextSpecificInstallationHook() {
		$application =& PKPApplication::getApplication();

		if ($application->getContextDepth() == 0) return null;

		$contextList = $application->getContextList();
		return ucfirst(array_shift($contextList)).'SiteSettingsForm::execute';
	}

	/**
	 * Retrieve a plugin setting within the given context
	 * @param $context array an array of context ids
	 * @param $name the setting name
	 */
	function getSetting($context, $name) {
		if (!Config::getVar('general', 'installed')) return null;

		// Check that the context has the correct depth
		$application =& PKPApplication::getApplication();
		assert(is_array($context) && $application->getContextDepth() == count($context));

		// Construct the argument list and call the plug-in settings DAO
		$arguments = $context;
		$arguments[] = $this->getName();
		$arguments[] = $name;
		$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO');
		return call_user_func_array(array(&$pluginSettingsDao, 'getSetting'), $arguments);
	}

	/**
	 * Update a plugin setting within the given context.
	 * @param $context array an array of context ids
	 * @param $name the setting name
	 * @param $value mixed
	 * @param $type string optional
	 */
	function updateSetting($context, $name, $value, $type = null) {
		// Check that the context has the correct depth
		$application =& PKPApplication::getApplication();
		assert(is_array($context) && $application->getContextDepth() == count($context));

		// Construct the argument list and call the plug-in settings DAO
		$arguments = $context;
		$arguments[] = $this->getName();
		$arguments[] = $name;
		$arguments[] = $value;
		$arguments[] = $type;
		$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO');
		call_user_func_array(array(&$pluginSettingsDao, 'updateSetting'), $arguments);
	}

	/**
	 * Callback used to install settings on system install.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installSiteSettings($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		// All contexts are set to zero for site-wide plug-in settings
		$application =& PKPApplication::getApplication();
		$arguments = array_fill(0, $application->getContextDepth(), 0);
		$arguments[] = $this->getName();
		$arguments[] = $this->getInstallSitePluginSettingsFile();
		$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO');
		call_user_func_array(array(&$pluginSettingsDao, 'installSettings'), $arguments);

		return false;
	}

	/**
	 * Load locale data for this plugin.
	 * @param $locale string
	 * @return boolean
	 */
	function addLocaleData($locale = null) {
		if ($locale == '') $locale = AppLocale::getLocale();
		$localeFilename = $this->getLocaleFilename($locale);
		if ($localeFilename) {
			AppLocale::registerLocaleFile($locale, $this->getLocaleFilename($locale));
			return true;
		}
		return false;
	}

	/**
	 * Get the filename for the locale data for this plugin.
	 * @param $locale string
	 * @return string
	 */
	function getLocaleFilename($locale) {
		return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'locale.xml';
	}

	/**
	 * Add help data for this plugin.
	 * @param $locale string
	 * @return boolean
	 */
	function addHelpData($locale = null) {
		if ($locale == '') $locale = AppLocale::getLocale();
		import('help.Help');
		$help =& Help::getHelp();
		import('help.PluginHelpMappingFile');
		$pluginHelpMapping = new PluginHelpMappingFile($this);
		$help->addMappingFile($pluginHelpMapping);
		return true;
	}

	/**
	 * Get the path and filename of the help mapping file, if this
	 * plugin includes help files.
	 * @return string
	 */
	function getHelpMappingFilename() {
		return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'help.xml';
	}

	/**
	 * Determine whether or not this plugin should be hidden from the
	 * management interface. Useful in the case of derivative plugins,
	 * i.e. when a generic plugin registers a feed plugin.
	 */
	function getHideManagement() {
		return false;
	}

	/**
	 * Get the name of this plugin. The name must be unique within
	 * its category, and should be suitable for part of a filename
	 * (ie short, no spaces, and no dependencies on cases being unique).
	 * @return String name of plugin
	 */
	function getName() {
		return 'Plugin';
	}

	/**
	 * Get the display name for this plugin.
	 * @return string
	 */
	function getDisplayName() {
		return $this->getName();
	}

	/**
	 * Get a description of this plugin.
	 */
	function getDescription() {
		return 'This is the base plugin class. It contains no concrete implementation. Its functions must be overridden by subclasses to provide actual functionality.';
	}

	/**
	 * Load a PHP file from this plugin's installation directory.
	 * @param $class string
	 */
	function import($class) {
		require_once($this->getPluginPath() . '/' . str_replace('.', '/', $class) . '.inc.php');
	}

	/**
	 * Site-wide plugins should override this function to return true.
	 */
	function isSitePlugin() {
		return false;
	}

	/**
	 * Get a list of management actions in the form of a page => value pair.
	 * The management actions from this list are passed to the manage() function
	 * when called.
	 */
	function getManagementVerbs() {
		return null;
	}

	/**
	 * Perform a management function.
	 */
	function manage($verb, $args) {
		return false;
	}

	/**
	 * Extend the {url ...} smarty to support plugins.
	 */
	function smartyPluginUrl($params, &$smarty) {
		$path = array($this->getCategory(), $this->getName());
		if (is_array($params['path'])) {
			$params['path'] = array_merge($path, $params['path']);
		} elseif (!empty($params['path'])) {
			$params['path'] = array_merge($path, array($params['path']));
		} else {
			$params['path'] = $path;
		}
		return $smarty->smartyUrl($params, $smarty);
	}

	/**
	 * Get the filename of the ADODB schema for this plugin.
	 * Subclasses using SQL tables should override this.
	 * @return string
	 */
	function getInstallSchemaFile() {
		return null;
	}

	/**
	 * Called during the install process to install the plugin schema,
	 * if applicable.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function updateSchema($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		$schemaXMLParser = new adoSchema($installer->dbconn);
		$dict =& $schemaXMLParser->dict;
		$dict->SetCharSet($installer->dbconn->charSet);
		$sql = $schemaXMLParser->parseSchema($this->getInstallSchemaFile());
		if ($sql) {
			$result = $installer->executeSQL($sql);
		} else {
			$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallSchemaFile(), __('installer.installParseDBFileError')));
			$result = false;
		}
		return false;
	}

	/**
	 * Get the filename of the settings data for this plugin to install
	 * when a new application context (e.g. journal, conference or press)
	 * is installed.
	 * Subclasses using default settings should override this.
	 * @return string
	 */
	function getContextSpecificPluginSettingsFile() {
		return null;
	}

	/**
	 * Get the filename of the settings data for this plugin to install
	 * when the system is installed (i.e. site-level plugin settings).
	 * Subclasses using default settings should override this.
	 * @return string
	 */
	function getInstallSitePluginSettingsFile() {
		return null;
	}

	/**
	 * Get the filename of the email templates for this plugin.
	 * Subclasses using email templates should override this.
	 * @return string
	 */
	function getInstallEmailTemplatesFile() {
		return null;
	}

	/**
	 * Get the filename of the email template data for this plugin.
	 * Subclasses using email templates should override this.
	 * @return string
	 */
	function getInstallEmailTemplateDataFile() {
		return null;
	}

	/**
	 * Get the filename of the install data for this plugin.
	 * Subclasses using SQL tables should override this.
	 * @return string
	 */
	function getInstallDataFile() {
		return null;
	}

	/**
	 * Callback used to install settings on new context
	 * (e.g. journal, conference or press) creation.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installContextSpecificSettings($hookName, $args) {
		// Only applications that have at least one context can
		// install context specific settings.
		$application =& PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();
		if ($contextDepth > 0) {
			$context =& $args[1];

			// Make sure that this is really a new context
			$isNewContext = isset($args[3]) ? $args[3] : true;
			if (!$isNewContext) return false;

			// Install context specific settings
			$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO');
			switch ($contextDepth) {
				case 1:
					$pluginSettingsDao->installSettings($context->getId(), $this->getName(), $this->getContextSpecificPluginSettingsFile());
					break;

				case 2:
					$pluginSettingsDao->installSettings($context->getId(), 0, $this->getName(), $this->getContextSpecificPluginSettingsFile());
					break;

				default:
					// No application can have a context depth > 2
					assert(false);
			}
		}
		return false;
	}

	/**
	 * Callback used to install email templates.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installEmailTemplates($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$sql = $emailTemplateDao->installEmailTemplates($this->getInstallEmailTemplatesFile(), true);
		if ($sql) {
			$result = $installer->executeSQL($sql);
		} else {
			$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallDataFile(), __('installer.installParseEmailTemplatesFileError')));
			$result = false;
		}
		return false;
	}

	/**
	 * Callback used to install email template data.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installEmailTemplateData($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		foreach ($installer->installedLocales as $locale) {
			$filename = str_replace('{$installedLocale}', $locale, $this->getInstallEmailTemplateDataFile());
			if (!file_exists($filename)) continue;
			$sql = $emailTemplateDao->installEmailTemplateData($filename, true);
			if ($sql) {
				$result = $installer->executeSQL($sql);
			} else {
				$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallDataFile(), __('installer.installParseEmailTemplatesFileError')));
				$result = false;
			}
		}
		return false;
	}

	/**
	 * Callback used to install email template data on locale install.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installLocale($hookName, $args) {
		$locale =& $args[0];
		$filename = str_replace('{$installedLocale}', $locale, $this->getInstallEmailTemplateDataFile());
		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$emailTemplateDao->installEmailTemplateData($filename);
		return false;
	}

	/**
	 * Callback used to install data files.
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installData($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		$sql = $installer->dataXMLParser->parseData($this->getInstallDataFile());
		if ($sql) {
			$result = $installer->executeSQL($sql);
		} else {
			$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallDataFile(), __('installer.installParseDBFileError')));
			$result = false;
		}
		return false;
	}

	/**
	 * Get the current version of this plugin
	 * @return object Version
	 */
	function getCurrentVersion() {
		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$product = basename($this->getPluginPath());
		$installedPlugin = $versionDao->getCurrentVersion($product);

		if ($installedPlugin) {
			return $installedPlugin;
		} else {
			return false;
		}
	}
}

?>
