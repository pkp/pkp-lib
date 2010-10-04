<?php

/**
 * @defgroup plugins
 */

/**
 * @file classes/plugins/PKPPlugin.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPPlugin
 * @ingroup plugins
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


// Define the well-known file name for filter configuration data.
define('PLUGIN_FILTER_DATAFILE', 'filterConfig.xml');

class PKPPlugin {
	/** @var $pluginPath string Path name to files for this plugin */
	var $pluginPath;

	/** @var $pluginCategory string Category name this plugin is registered to*/
	var $pluginCategory;

	/**
	 * Constructor
	 */
	function PKPPlugin() {
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
	 * @param $category String Name of category plugin was registered to
	 * @param $path String The path the plugin was found in
	 * @return boolean True iff plugin registered successfully; if false,
	 * 	the plugin will not be executed.
	 */
	function register($category, $path) {
		$this->pluginPath = $path;
		$this->pluginCategory = $category;
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
		HookRegistry::register ('Installer::postInstall', array(&$this, 'installFilters'));
		return true;
	}

	/*
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
	function getName() {
		assert(false);
	}

	/**
	 * Get the display name for this plugin.
	 *
	 * @return string
	 */
	function getDisplayName() {
		assert(false);
	}

	/**
	 * Get a description of this plugin.
	 *
	 * @return string
	 */
	function getDescription() {
		assert(false);
	}

	//
	// Plugin Behavior and Management
	//

	/**
	 * Return a number indicating the sequence in which this plugin
	 * should be registered compared to others of its category.
	 * Higher = later.
	 *
	 * @return integer
	 */
	function getSeq() {
		return 0;
	}

	/**
	 * Site-wide plugins should override this function to return true.
	 *
	 * @return boolean
	 */
	function isSitePlugin() {
		return false;
	}

	/**
	 * Get a list of management actions in the form of a page => value pair.
	 * The management actions from this list are passed to the manage() function
	 * when called.
	 *
	 * @return array
	 */
	function getManagementVerbs() {
		return null;
	}

	/**
	 * Perform a management function.
	 *
	 * @param $verb string
	 * @param $args array
	 * @param $message string If a message is returned from this by-ref argument then
	 *  it will be displayed as a notification if (and only if) the method returns
	 *  false.
	 * @return boolean will redirect to the plugin category page if false, otherwise
	 *  will remain on the same page
	 */
	function manage($verb, $args, &$message) {
		return false;
	}

	/**
	 * Determine whether or not this plugin should be hidden from the
	 * management interface. Useful in the case of derivative plugins,
	 * i.e. when a generic plugin registers a feed plugin.
	 *
	 * @return boolean
	 */
	function getHideManagement() {
		return false;
	}

	//
	// Plugin Installation
	//

	/**
	 * Get the filename of the ADODB schema for this plugin.
	 * Subclasses using SQL tables should override this.
	 *
	 * @return string
	 */
	function getInstallSchemaFile() {
		return null;
	}

	/**
	 * Get the filename of the install data for this plugin.
	 * Subclasses using SQL tables should override this.
	 *
	 * @return string|array|null one or more data files to be installed.
	 */
	function getInstallDataFile() {
		return null;
	}

	/**
	 * Get the filename of the settings data for this plugin to install
	 * when the system is installed (i.e. site-level plugin settings).
	 * Subclasses using default settings should override this.
	 *
	 * @return string
	 */
	function getInstallSitePluginSettingsFile() {
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
	function getContextSpecificPluginSettingsFile() {
		return null;
	}

	/**
	 * Get the filename of the email templates for this plugin.
	 * Subclasses using email templates should override this.
	 *
	 * @return string
	 */
	function getInstallEmailTemplatesFile() {
		return null;
	}

	/**
	 * Get the filename of the email template data for this plugin.
	 * Subclasses using email templates should override this.
	 *
	 * @return string
	 */
	function getInstallEmailTemplateDataFile() {
		return null;
	}

	/*
	 * Protected helper methods (can be used by custom plugins but
	 * should not be overridden by custom plugins)
	 */
	/**
	 * Get the name of the category this plugin is registered to.
	 * @return String category
	 */
	function getCategory() {
		return $this->pluginCategory;
	}

	/**
	 * Get the path this plugin's files are located in.
	 * @return String pathname
	 */
	function getPluginPath() {
		return $this->pluginPath;
	}

	/**
	 * Return the canonical template path of this plug-in
	 *
	 * @return string
	 */
	function getTemplatePath() {
		$basePath = dirname(dirname(dirname(dirname(dirname(__FILE__)))));
		return "file:$basePath/" . $this->getPluginPath() . '/';
	}

	/**
	 * Load locale data for this plugin.
	 *
	 * @param $locale string
	 * @return boolean
	 */
	function addLocaleData($locale = null) {
		if ($locale == '') $locale = Locale::getLocale();
		$localeFilename = $this->getLocaleFilename($locale);
		if ($localeFilename) {
			Locale::registerLocaleFile($locale, $this->getLocaleFilename($locale));
			return true;
		}
		return false;
	}

	/**
	 * Add help data for this plugin.
	 *
	 * @param $locale string
	 * @return boolean
	 */
	function addHelpData($locale = null) {
		if ($locale == '') $locale = Locale::getLocale();
		import('classes.help.Help');
		$help =& Help::getHelp();
		import('lib.pkp.classes.help.PluginHelpMappingFile');
		$pluginHelpMapping = new PluginHelpMappingFile($this);
		$help->addMappingFile($pluginHelpMapping);
		return true;
	}

	/**
	 * Retrieve a plugin setting within the given context
	 *
	 * @param $context array an array of context ids
	 * @param $name the setting name
	 */
	function getContextSpecificSetting($context, $name) {
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
	 *
	 * @param $context array an array of context ids
	 * @param $name the setting name
	 * @param $value mixed
	 * @param $type string optional
	 */
	function updateContextSpecificSetting($context, $name, $value, $type = null) {
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
	 * Load a PHP file from this plugin's installation directory.
	 *
	 * @param $class string
	 */
	function import($class) {
		require_once($this->getPluginPath() . '/' . str_replace('.', '/', $class) . '.inc.php');
	}

	/*
	 * Protected helper methods (for internal use only, should not
	 * be used by custom plug-ins)
	 *
	 * NB: These methods may change without notice in the future!
	 */
	/**
	 * Generate the context for this plug-in's generic
	 * settings. This is array with the id of the main context
	 * (e.g. journal, conference or press) as the first entry
	 * and all remaining entries set to 0. If the calling
	 * application doesn't support context then the this will
	 * return an empty array (e.g. harvester).
	 *
	 * For site-wide plug-ins the context will be set to 0.
	 *
	 * @return array
	 */
	function getSettingMainContext() {
		$application =& PKPApplication::getApplication();
		$contextDepth = $application->getContextDepth();

		$settingContext = array();
		if ($contextDepth > 0) {
			if ($this->isSitePlugin()) {
				$mainContext = null;
			} else {
				$request =& $application->getRequest();
				$router =& $request->getRouter();
				// Try to identify the main context (e.g. journal, conference, press),
				// will be null if none found.
				$mainContext =& $router->getContext($request, 1);
			}

			// Create the context for the setting if found
			if ($mainContext) $settingContext[] = $mainContext->getId();
			$settingContext = array_pad($settingContext, $contextDepth, 0);
		}
		return $settingContext;
	}

	/**
	 * Get the filename for the locale data for this plugin.
	 *
	 * @param $locale string
	 * @return string
	 */
	function getLocaleFilename($locale) {
		return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'locale' . DIRECTORY_SEPARATOR . $locale . DIRECTORY_SEPARATOR . 'locale.xml';
	}

	/**
	 * Get the path and filename of the help mapping file, if this
	 * plugin includes help files.
	 *
	 * @return string
	 */
	function getHelpMappingFilename() {
		return $this->getPluginPath() . DIRECTORY_SEPARATOR . 'help.xml';
	}

	/**
	 * Callback used to install data files.
	 *
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installData($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		// Treat single and multiple data files uniformly.
		$dataFiles = $this->getInstallDataFile();
		if (is_scalar($dataFiles)) $dataFiles = array($dataFiles);

		// Install all data files.
		foreach($dataFiles as $dataFile) {
			$sql = $installer->dataXMLParser->parseData($dataFile);
			if ($sql) {
				$result = $installer->executeSQL($sql);
			} else {
				$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallDataFile(), Locale::translate('installer.installParseDBFileError')));
				$result = false;
			}
			if (!$result) return false;
		}
		return false;
	}

	/**
	 * Callback used to install settings on system install.
	 *
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installSiteSettings($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		// Settings are only installed during automated installs. We issue a warning
		// to the user to run an upgrade after manual installation.
		if (!$installer->getParam('manualInstall')) {
			// All contexts are set to zero for site-wide plug-in settings
			$application =& PKPApplication::getApplication();
			$contextDepth = $application->getContextDepth();
			if ($contextDepth >0) {
				$arguments = array_fill(0, $contextDepth, 0);
			} else {
				$arguments = array();
			}
			$arguments[] = $this->getName();
			$arguments[] = $this->getInstallSitePluginSettingsFile();
			$pluginSettingsDao =& DAORegistry::getDAO('PluginSettingsDAO');
			call_user_func_array(array(&$pluginSettingsDao, 'installSettings'), $arguments);
		}

		return false;
	}

	/**
	 * Callback used to install settings on new context
	 * (e.g. journal, conference or press) creation.
	 *
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
	 *
	 * @param $hookName string
	 * @param $args array
	 * @return boolean
	 */
	function installEmailTemplates($hookName, $args) {
		$installer =& $args[0];
		$result =& $args[1];

		$emailTemplateDao =& DAORegistry::getDAO('EmailTemplateDAO');
		$sql = $emailTemplateDao->installEmailTemplates($this->getInstallEmailTemplatesFile(), true, null, true);
		if ($sql) {
			$result = $installer->executeSQL($sql);
		} else {
			$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallDataFile(), Locale::translate('installer.installParseEmailTemplatesFileError')));
			$result = false;
		}
		return false;
	}

	/**
	 * Callback used to install email template data.
	 *
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
				$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallDataFile(), Locale::translate('installer.installParseEmailTemplatesFileError')));
				$result = false;
			}
		}
		return false;
	}

	/**
	 * Callback used to install email template data on locale install.
	 *
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
	 * Callback used to install filters.
	 * @param $hookName string
	 * @param $args array
	 */
	function installFilters($hookName, $args) {
		$result =& $args[1];

		// Construct the well-known filter configuration file names.
		$filterConfigFile = $this->getPluginPath().'/filter/'.PLUGIN_FILTER_DATAFILE;
		$filterConfigFiles = array(
			'./lib/pkp/'.$filterConfigFile,
			'./'.$filterConfigFile
		);

		// Run through the config file positions and see
		// whether one of these exists and needs to be installed.
		foreach($filterConfigFiles as $filterConfigFile) {
			// Is there a filter configuration?
			if (!file_exists($filterConfigFile)) continue;

			// Parse the filter configuration.
			$xmlParser = new XMLParser();
			$tree =& $xmlParser->parse($filterConfigFile);

			// Validate the filter configuration.
			if (!$tree) {
				$xmlParser->destroy();

				// Stop installation.
				$result = false;
				return true;
			}

			// Are there any filter groups to be installed?
			$filterGroupsNode =& $tree->getChildByName('filterGroups');
			if (is_a($filterGroupsNode, 'XMLNode')) {
				$this->_installFilterGroups($filterGroupsNode);
			}

			// Are there any filters to be installed?
			$filtersNode =& $tree->getChildByName('filters');
			if (is_a($filtersNode, 'XMLNode')) {
				foreach ($filtersNode->getChildren() as $filterNode) { /* @var $filterNode XMLNode */
					$this->_configureFilter($filterNode);
				}
			}

			// Get rid of the parser.
			$xmlParser->destroy();
			unset($xmlParser);
		}

		// Do not stop installation.
		return false;
	}

	/**
	 * Called during the install process to install the plugin schema,
	 * if applicable.
	 *
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
			$installer->setError(INSTALLER_ERROR_DB, str_replace('{$file}', $this->getInstallSchemaFile(), Locale::translate('installer.installParseDBFileError')));
			$result = false;
		}
		return false;
	}

	/**
	 * Extend the {url ...} smarty to support plugins.
	 *
	 * @param $params array
	 * @param $smarty Smarty
	 * @return string
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
	 * Get the current version of this plugin
	 *
	 * @return Version
	 */
	function getCurrentVersion() {
		$versionDao =& DAORegistry::getDAO('VersionDAO');
		$product = basename($this->getPluginPath());
		$installedPlugin = $versionDao->getCurrentVersion($product, true);

		if ($installedPlugin) {
			return $installedPlugin;
		} else {
			return false;
		}
	}

	/*
	 * Private helper methods
	 */
	/**
	 * The application specific context installation hook.
	 *
	 * @return string
	 */
	function _getContextSpecificInstallationHook() {
		$application =& PKPApplication::getApplication();

		if ($application->getContextDepth() == 0) return null;

		$contextList = $application->getContextList();
		return ucfirst(array_shift($contextList)).'SiteSettingsForm::execute';
	}

	/**
	 * Helper method that installs filter groups based on
	 * the given XML node which represents a <filterGroups>
	 * element.
	 * @param $filterGroupsNode XMLNode
	 */
	function _installFilterGroups($filterGroupsNode) {
		// Install filter groups.
		$filterGroupDao =& DAORegistry::getDAO('FilterGroupDAO'); /* @var $filterGroupDao FilterGroupDAO */
		import('lib.pkp.classes.filter.FilterGroup');

		foreach ($filterGroupsNode->getChildren() as $filterGroupNode) { /* @var $filterGroupNode XMLNode */
			$filterGroupSymbolic = $filterGroupNode->getAttribute('symbolic');

			// Make sure that the filter group has not been
			// installed before to guarantee idempotence.
			$existingFilterGroup =& $filterGroupDao->getObjectBySymbolic($filterGroupSymbolic);
			if (!is_null($existingFilterGroup)) continue;

			// Instantiate and configure the filter group.
			$filterGroup = new FilterGroup();
			$filterGroup->setSymbolic($filterGroupSymbolic);
			$filterGroup->setDisplayName($filterGroupNode->getAttribute('displayName'));
			$filterGroup->setDescription($filterGroupNode->getAttribute('description'));
			$filterGroup->setInputType($filterGroupNode->getAttribute('inputType'));
			$filterGroup->setOutputType($filterGroupNode->getAttribute('outputType'));

			// Install the filter group.
			$installedGroupId = $filterGroupDao->insertObject($filterGroup);
			assert(is_integer($installedGroupId));

			unset($filterGroup);
		}
	}

	/**
	 * Helper method that configures and optionally
	 * installs a filter based on the given XML node
	 * which represents a <filter> element.
	 * @param $filterNode XMLNode
	 * @param $persist boolean whether to install the filter
	 * @return PersistableFilter the installed filter.
	 */
	function &_configureFilter($filterNode, $persist = true) {
		// Install filters.
		$filterDao =& DAORegistry::getDAO('FilterDAO'); /* @var $filterDao FilterDAO */

		$filterGroupSymbolic = $filterNode->getAttribute('inGroup');
		$filterClassName = $filterNode->getAttribute('class');
		$isTemplate = $filterNode->getAttribute('isTemplate');

		// Make sure that the filter has not been
		// installed before to guarantee idempotence.
		$existingFilters =& $filterDao->getObjectsByGroupAndClass($filterGroupSymbolic, $filterClassName, 0, $isTemplate);
		$existingFilterCount = $existingFilters->getCount();
		if ($existingFilterCount == 1) {
			$existingFilter =& $existingFilters->next();
			return $existingFilter;
		} elseif($existingFilterCount > 1) {
			// FIXME: If we start to find duplicate existing filters here
			// then we'll have to search by displayName also when looking for
			// existing filters as our current criteria are then not selective
			// enough.
			fatalError('Duplicate existing filter found!');
		}

		$subNodes = $filterNode->getChildren();
		$settings = array();
		$subFilters = array();
		foreach($subNodes as $subNode) { /* @var $subNode XMLNode */
			switch($subNode->getName()) {
				case 'setting':
					// Get the filter setting.
					list($name, $value) = $this->_getFilterSetting($subNode);
					$settings[$name] = $value;
					unset($name, $value);
					break;

				case 'filter':
					// Recursively install sub-filters.
					$subFilter =& $this->_configureFilter($subNode, false);
					$subFilters[] =& $subFilter;
					unset($subFilter);
					break;
			}
		}

		// Configure (and optionally install) the filter.
		$installedFilter =& $filterDao->configureObject($filterClassName, $filterGroupSymbolic, $settings, $isTemplate, 0, $subFilters, $persist);
		assert(is_a($installedFilter, 'PersistableFilter'));
		return $installedFilter;
	}

	/**
	 * Helper method that extracts filter settings
	 * from the children of a <filter> element.
	 * @param $settingNode XMLNode
	 * @return $setting array a key-value pair.
	 */
	function _getFilterSetting($settingNode) {
		// Retrieve the setting name.
		$nameNode =& $settingNode->getChildByName('name');
		assert(is_a($nameNode, 'XMLNode'));
		$name = $nameNode->getValue();

		// Retrieve the setting value.
		$type = $settingNode->getAttribute('type');
		$valueNode =& $settingNode->getChildByName('value');
		assert(is_a($valueNode, 'XMLNode'));
		switch($type) {
			case 'string':
				$value = (string)$valueNode->getValue();
				break;

			case 'bool':
				$value = (boolean)$valueNode->getValue();
				break;

			case 'int':
				$value = (integer)$valueNode->getValue();
				break;

			case 'const':
				$constName = $valueNode->getValue();
				assert(defined($constName));
				$value = constant($constName);
				break;

			case 'object':
				$value = array();
				$arrayNode =& $valueNode->getChildByName('array');
				$value = $this->_readArraySetting($arrayNode);
				break;

			default:
				// Unknown type.
				assert(false);
				$value = null;
		}

		// Add the setting to the list.
		$setting = array($name, $value);
		return $setting;
	}

	/**
	 * Recursively read an array from an XML element list.
	 * @param $arrayNode XMLNode
	 * @return array
	 */
	function _readArraySetting($arrayNode) {
		$resultArray = array();
		foreach($arrayNode->getChildren() as $elementNode) { /* @var $elementNode XMLNode */
			$key = $elementNode->getAttribute('key');

			$subArrayNode = $elementNode->getChildByName('array');
			if (is_a($subArrayNode, 'XMLNode')) {
				// Recurse into sub-array.
				$value = $this->_readArraySetting($subArrayNode);
			} else {
				// Read the element value.
				$value = $elementNode->getValue();
			}

			// Add the element to the result set.
			if (isset($key)) {
				$resultArray[$key] = $value;
			} else {
				$resultArray[] = $value;
			}
		}

		return $resultArray;
	}
}

?>
