<?php

/**
 * @file controllers/grid/plugins/PluginGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGridHandler
 * @ingroup controllers_grid_plugins
 *
 * @brief Handle plugins grid requests.
 */

import('lib.pkp.classes.controllers.grid.CategoryGridHandler');

class PluginGridHandler extends CategoryGridHandler {
	/**
	 * Constructor
	 * @param $roles array
	 */
	function PluginGridHandler($roles) {
		if (is_null($roles)) {
			fatalError('Direct access not allowed!');
		}

		$this->addRoleAssignment($roles,
			array('fetchGrid, fetchCategory', 'fetchRow'));

		$this->addRoleAssignment(ROLE_ID_SITE_ADMIN,
			array('installPlugin', 'upgradePlugin', 'deletePlugin'));

		parent::CategoryGridHandler();
	}


	//
	// Overridden template methods
	//
	/**
	 * @see GridHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load language components
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_COMMON, LOCALE_COMPONENT_APP_MANAGER);

		// Basic grid configuration
		$this->setTitle('common.plugins');

		// Set the no items row text
		$this->setEmptyRowText('grid.noItems');

		// Columns
		import('lib.pkp.controllers.grid.plugins.PluginGridCellProvider');
		$pluginCellProvider = new PluginGridCellProvider();
		$this->addColumn(
			new GridColumn('name',
				'common.name',
				null,
				'controllers/grid/gridCell.tpl',
				$pluginCellProvider,
				array('multiline' => true)
			)
		);

		$descriptionColumn = new GridColumn(
				'description',
				'common.description',
				null,
				'controllers/grid/gridCell.tpl',
				$pluginCellProvider
		);
		$descriptionColumn->addFlag('html', true);
		$this->addColumn($descriptionColumn);

		$this->addColumn(
			new GridColumn('enabled',
				'common.enabled',
				null,
				'controllers/grid/common/cell/selectStatusCell.tpl',
				$pluginCellProvider
			)
		);

		$router = $request->getRouter();

		// Grid level actions.
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
			import('lib.pkp.classes.linkAction.request.AjaxModal');

			// Install plugin.
			$this->addAction(
				new LinkAction(
					'install',
					new AjaxModal(
						$router->url($request, null, null, 'installPlugin'),
						__('manager.plugins.install'), 'modal_add_file'),
					__('manager.plugins.install'),
					'add'));
		}
	}

	/**
	 * @see GridHandler::getFilterForm()
	 */
	function getFilterForm() {
		return 'controllers/grid/plugins/pluginGridFilter.tpl';
	}

	/**
	 * @see GridHandler::getFilterSelectionData()
	 */
	function getFilterSelectionData($request) {
		$category = $request->getUserVar('category');
		$pluginName = $request->getUserVar('pluginName');

		if (is_null($category)) {
			$category = 'all';
		}

		return array('category' => $category, 'pluginName' => $pluginName);
	}

	/**
	 * @see GridHandler::renderFilter()
	 */
	function renderFilter($request) {
		$categoriesSymbolic = $this->loadData($request, null);
		$categories = array('all' => __('grid.plugin.allCategories'));
		foreach ($categoriesSymbolic as $category) {
			$categories[$category] = __("plugins.categories.$category");
		}
		$filterData = array('categories' => $categories);

		return parent::renderFilter($request, $filterData);
	}

	/**
	 * @see CategoryGridHandler::getCategoryRowInstance()
	 */
	function getCategoryRowInstance() {
		import('lib.pkp.controllers.grid.plugins.PluginCategoryGridRow');
		return new PluginCategoryGridRow();
	}

	/**
	 * @see CategoryGridHandler::getCategoryData()
	 */
	function getCategoryData($categoryDataElement, $filter) {
		$plugins =& PluginRegistry::loadCategory($categoryDataElement);

		$versionDao = DAORegistry::getDAO('VersionDAO');
		import('lib.pkp.classes.site.VersionCheck');
		$fileManager = new FileManager();

		foreach ($plugins as $plugin) {
			$version = $plugin->getCurrentVersion();
			if ($version == null) { // this plugin is on the file system, but not installed.
				$versionFile = $plugin->getPluginPath() . '/version.xml';
				if ($fileManager->fileExists($versionFile)) {
					$versionInfo = VersionCheck::parseVersionXML($versionFile);
					$pluginVersion = $versionInfo['version'];
				} else {
					$pluginVersion = new Version(
						1, 0, 0, 0, // Major, minor, revision, build
						Core::getCurrentDate(), // Date installed
						1,	// Current
						'plugins.'.$category, // Type
						basename($plugin->getPluginPath()), // Product
						'',	// Class name
						0,	// Lazy load
						$plugin->isSitePlugin()	// Site wide
					);
				}
				$versionDao->insertVersion($pluginVersion, true);
			}
		}

		if (!is_null($filter) && isset($filter['pluginName']) && $filter['pluginName'] != "") {
			// Find all plugins that have the filter name string in their display names.
			$filteredPlugins = array();
			foreach ($plugins as $plugin) { /* @var $plugin Plugin */
				$pluginName = $plugin->getDisplayName();
				if (stristr($pluginName, $filter['pluginName']) !== false) {
					$filteredPlugins[$plugin->getName()] = $plugin;
				}
			}
			return $filteredPlugins;
		}

		return $plugins;
	}

	/**
	 * @see CategoryGridHandler::getCategoryRowIdParameterName()
	 */
	function getCategoryRowIdParameterName() {
		return 'category';
	}

	/**
	 * @see GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		$categories = PluginRegistry::getCategories();
		if (is_array($filter) && isset($filter['category']) && array_search($filter['category'], $categories) !== false) {
			return array($filter['category'] => $filter['category']);
		} else {
			return array_combine($categories, $categories);
		}
	}


	//
	// Public handler methods.
	//
	/**
	 * Perform plugin-specific management functions.
	 * @param $args array
	 * @param $request object
	 */
	function plugin($args, $request) {
		$verb = (string) $request->getUserVar('verb');

		$this->setupTemplate($request, true);

		$plugin = $this->getAuthorizedContextObject(ASSOC_TYPE_PLUGIN); /* @var $plugin Plugin */
		$message = null;
		$pluginModalContent = null;
		if (!is_a($plugin, 'Plugin') || !$plugin->manage($verb, $args, $message, $messageParams, $pluginModalContent)) {
			if ($message) {
				$notificationManager = new NotificationManager();
				$user = $request->getUser();
				$notificationManager->createTrivialNotification($user->getId(), $message, $messageParams);

				return DAO::getDataChangedEvent($request->getUserVar('plugin'), $request->getUserVar($this->getCategoryRowIdParameterName()));
			}
		}
		if ($pluginModalContent) {
			$json = new JSONMessage(true, $pluginModalContent);
			$json->setEvent('refreshForm', $pluginModalContent);
			return $json->getString();
		}
	}

	/**
	 * Show upload plugin form to install a new plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function installPlugin($args, $request) {
		return $this->_showUploadPluginForm('install', $request);
	}

	/**
	 * Show upload plugin form to update an existing plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function upgradePlugin($args, $request) {
		return $this->_showUploadPluginForm('upgrade', $request);
	}

	/**
	 * Upload a plugin file.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function uploadPlugin($args, $request) {
		import('lib.pkp.classes.file.TemporaryFileManager');
		$temporaryFileManager = new TemporaryFileManager();
		$user = $request->getUser();

		// Return the temporary file id.
		if ($temporaryFile = $temporaryFileManager->handleUpload('uploadedFile', $user->getId())) {
			$json = new JSONMessage(true);
			$json->setAdditionalAttributes(array(
				'temporaryFileId' => $temporaryFile->getId()
			));
		} else {
			$json = new JSONMessage(false, __('manager.plugins.uploadError'));
		}

		return $json->getString();
	}

	/**
	 * Save upload plugin file form.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function saveUploadPlugin($args, $request) {
		$function = $request->getUserVar('function');

		import('lib.pkp.controllers.grid.plugins.form.UploadPluginForm');
		$uploadPluginForm = new UploadPluginForm($function);
		$uploadPluginForm->readInputData();

		if($uploadPluginForm->validate()) {
			if($uploadPluginForm->execute($request)) {
				return DAO::getDataChangedEvent();
			}
		}

		$json = new JSONMessage(false);
		return $json->getString();
	}

	/**
	 * Delete plugin.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function deletePlugin($args, $request) {
		$this->setupTemplate($request);
		$plugin =& $this->getAuthorizedContextObject(ASSOC_TYPE_PLUGIN);
		$category = $plugin->getCategory();
		$productName = basename($plugin->getPluginPath());

		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		$installedPlugin = $versionDao->getCurrentVersion('plugins.'.$category, $productName, true);

		$notificationMgr = new NotificationManager();
		$user = $request->getUser();

		if ($installedPlugin) {
			$pluginDest = Core::getBaseDir() . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $productName;
			$pluginLibDest = Core::getBaseDir() . DIRECTORY_SEPARATOR . PKP_LIB_PATH . DIRECTORY_SEPARATOR . 'plugins' . DIRECTORY_SEPARATOR . $category . DIRECTORY_SEPARATOR . $productName;

			// make sure plugin type is valid and then delete the files
			if (in_array($category, PluginRegistry::getCategories())) {
				// Delete the plugin from the file system.
				$fileManager = new FileManager();
				$fileManager->rmtree($pluginDest);
				$fileManager->rmtree($pluginLibDest);
			}

			if(is_dir($pluginDest) || is_dir($pluginLibDest)) {
				$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('manager.plugins.deleteError', array('pluginName' => $plugin->getDisplayName()))));
			} else {
				$versionDao->disableVersion('plugins.'.$category, $productName);
				$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('manager.plugins.deleteSuccess', array('pluginName' => $plugin->getDisplayName()))));
			}
		} else {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => __('manager.plugins.doesNotExist', array('pluginName' => $plugin->getDisplayName()))));
		}

		return DAO::getDataChangedEvent($plugin->getName());
	}

	/**
	 * Fetch upload plugin form.
	 * @param $function string
	 * @return string
	 */
	function _showUploadPluginForm($function, $request) {
		$this->setupTemplate($request, true);

		import('lib.pkp.controllers.grid.plugins.form.UploadPluginForm');
		$uploadPluginForm = new UploadPluginForm($function);
		$uploadPluginForm->initData();

		$json = new JSONMessage(true, $uploadPluginForm->fetch($request));
		return $json->getString();
	}
}

?>
