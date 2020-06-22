<?php

/**
 * @file controllers/grid/settings/pluginGallery/PluginGalleryGridHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryGridHandler
 * @ingroup controllers_grid_settings_pluginGallery
 *
 * @brief Handle review form grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

/**
 * Global value for 'all' category string value
 */
define('PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE', 'all');

class PluginGalleryGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
			array('fetchGrid', 'fetchRow', 'viewPlugin')
		);
		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN),
			array('installPlugin', 'upgradePlugin')
		);
	}


	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @copydoc GridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_DEFAULT);

		// Basic grid configuration.
		$this->setTitle('manager.plugins.pluginGallery');

		//
		// Grid columns.
		//
		import('lib.pkp.controllers.grid.plugins.PluginGalleryGridCellProvider');
		$pluginGalleryGridCellProvider = new PluginGalleryGridCellProvider();

		// Plugin name.
		$this->addColumn(
			new GridColumn(
				'name',
				'common.name',
				null,
				null,
				$pluginGalleryGridCellProvider
			)
		);

		// Description.
		$this->addColumn(
			new GridColumn(
				'summary',
				'common.description',
				null,
				null,
				$pluginGalleryGridCellProvider,
				array('width' => 50, 'alignment' => COLUMN_ALIGNMENT_LEFT)
			)
		);

		// Status.
		$this->addColumn(
			new GridColumn(
				'status',
				'common.status',
				null,
				null,
				$pluginGalleryGridCellProvider,
				array('width' => 20)
			)
		);
	}

	/**
	 * @see PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Implement methods from GridHandler.
	//
	/**
	 * @see GridHandler::loadData()
	 * @param $request PKPRequest Request object
	 * @param $filter array Filter parameters
	 * @return array Grid data.
	 */
	protected function loadData($request, $filter) {
		// Get all plugins.
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO'); /* @var $pluginGalleryDao PluginGalleryDAO */
		return $pluginGalleryDao->getNewestCompatible(
			Application::get(),
			$request->getUserVar('category'),
			$request->getUserVar('pluginText')
		);
	}

	/**
	 * @see GridHandler::getFilterForm()
	 */
	protected function getFilterForm() {
		return 'controllers/grid/plugins/pluginGalleryGridFilter.tpl';
	}

	/**
	 * @see GridHandler::getFilterSelectionData()
	 */
	function getFilterSelectionData($request) {
		$category = $request->getUserVar('category');
		$pluginName = $request->getUserVar('pluginText');

		if (is_null($category)) {
			$category = PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE;
		}

		return array('category' => $category, 'pluginText' => $pluginName);
	}

	/**
	 * @copydoc GridHandler::renderFilter()
	 */
	protected function renderFilter($request, $filterData = array()) {
		$categoriesSymbolic = $categories = PluginRegistry::getCategories();
		$categories = array(PLUGIN_GALLERY_ALL_CATEGORY_SEARCH_VALUE => __('grid.plugin.allCategories'));
		foreach ($categoriesSymbolic as $category) {
			$categories[$category] = __("plugins.categories.$category");
		}
		$filterData['categories'] = $categories;

		return parent::renderFilter($request, $filterData);
	}

	//
	// Public operations
	//
	/**
	 * View a plugin's details
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewPlugin($args, $request) {
		$plugin = $this->_getSpecifiedPlugin($request);

		// Display plugin information
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('plugin', $plugin);

		// Get currently installed version, if any.
		$installActionKey = $installConfirmKey = $installOp = null;
		switch ($plugin->getCurrentStatus()) {
			case PLUGIN_GALLERY_STATE_NEWER:
				$statusKey = 'manager.plugins.installedVersionNewer';
				$statusClass = 'newer';
				break;
			case PLUGIN_GALLERY_STATE_UPGRADABLE:
				$statusKey = 'manager.plugins.installedVersionOlder';
				$statusClass = 'older';
				$installActionKey='grid.action.upgrade';
				$installOp = 'upgradePlugin';
				$installConfirmKey = 'manager.plugins.upgradeConfirm';
				break;
			case PLUGIN_GALLERY_STATE_CURRENT:
				$statusKey = 'manager.plugins.installedVersionNewest';
				$statusClass = 'newest';
				break;
			case PLUGIN_GALLERY_STATE_AVAILABLE:
				$statusKey = 'manager.plugins.noInstalledVersion';
				$statusClass = 'notinstalled';
				$installActionKey='grid.action.install';
				$installOp = 'installPlugin';
				$installConfirmKey = 'manager.plugins.installConfirm';
				break;
			case PLUGIN_GALLERY_STATE_INCOMPATIBLE:
				$statusKey = 'manager.plugins.noCompatibleVersion';
				$statusClass = 'incompatible';
				break;
			default: return assert(false);
		}
		$templateMgr->assign('statusKey', $statusKey);
		$templateMgr->assign('statusClass', $statusClass);

		$router = $request->getRouter();
		if (Validation::isSiteAdmin() && $installOp) $templateMgr->assign('installAction', new LinkAction(
			'installPlugin',
			new RemoteActionConfirmationModal(
				$request->getSession(),
				__($installConfirmKey),
				__($installActionKey),
				$router->url($request, null, null, $installOp, null, array('rowId' => $request->getUserVar('rowId'))),
				'modal_information'
			),
			__($installActionKey),
			null
		));
		return new JSONMessage(true, $templateMgr->fetch('controllers/grid/plugins/viewPlugin.tpl'));
	}

	/**
	 * Upgrade a plugin
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function upgradePlugin($args, $request) {
		return $this->installPlugin($args, $request, true);
	}

	/**
	 * Install or upgrade a plugin
	 * @param $args array
	 * @param $request PKPRequest
	 * @param $isUpgrade boolean
	 */
	function installPlugin($args, $request, $isUpgrade = false) {
		$redirectUrl = $request->getDispatcher()->url($request, ROUTE_PAGE, null, 'management', 'settings', array('website'), array('r' => uniqid()), 'plugins');
		if (!$request->checkCSRF()) return $request->redirectUrlJson($redirectUrl);

		$plugin = $this->_getSpecifiedPlugin($request);
		$notificationMgr = new NotificationManager();
		$user = $request->getUser();

		// Download the file and ensure the MD5 sum
		$destPath = tempnam(sys_get_temp_dir(), 'plugin');

		// Download the plugin package.
		try {
			$wrapper = FileWrapper::wrapper($plugin->getReleasePackage());
			while (true) {
				$newWrapper = $wrapper->open();
				if (is_a($newWrapper, 'FileWrapper')) {
					// Follow a redirect
					$wrapper = $newWrapper;
				} elseif (!$newWrapper) {
					throw new Exception('Unable to open plugin URL!');
				} else {
					// OK, we've found the end result
					break;
				}
			}

			if (!$wrapper->save($destPath)) throw new Exception('Unable to save plugin to local file!');
			$wrapper->close();
		} catch (Exception $e) {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $e->getMessage()));
			return $request->redirectUrlJson($redirectUrl);
		}

		// Verify the plugin checksum.
		if (md5_file($destPath) !== $plugin->getReleaseMD5()) {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => 'Incorrect MD5 checksum!'));
			unlink($destPath);
			return $request->redirectUrlJson($redirectUrl);
		}

		// Extract the plugin
		import('lib.pkp.classes.plugins.PluginHelper');
		$pluginHelper = new PluginHelper();
		try {
			$pluginDir = $pluginHelper->extractPlugin($destPath, $plugin->getProduct() . '-' . $plugin->getVersion());
		} catch (Exception $e) {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $e->getMessage()));
			return $request->redirectUrlJson($redirectUrl);
		} finally {
			unlink($destPath);
		}

		// Install or upgrade the plugin
		try {
			if (!$isUpgrade) {
				$pluginVersion = $pluginHelper->installPlugin($pluginDir);
			} else {
				$pluginVersion = $pluginHelper->upgradePlugin($plugin->getCategory(), $plugin->getProduct(), $pluginDir);
			}

			// Notify of success.
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('manager.plugins.upgradeSuccessful', array('versionString' => $pluginVersion->getVersionString(false)))));
		} catch (Exception $e) {
			$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('contents' => $e->getMessage()));
			if (!$isUpgrade) {
				import('lib.pkp.classes.file.TemporaryFileManager');
				$temporaryFileManager = new TemporaryFileManager();
				$temporaryFileManager->rmtree($pluginDir);
			}
		}
		return $request->redirectUrlJson($redirectUrl);
	}

	/**
	 * Get the specified plugin.
	 * @param $request PKPRequest
	 * @return GalleryPlugin
	 */
	function _getSpecifiedPlugin($request) {
		// Get all plugins.
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO'); /* @var $pluginGalleryDao PluginGalleryDAO */
		$plugins = $pluginGalleryDao->getNewestCompatible(Application::get());

		// Get specified plugin. Indexes into $plugins are 0-based
		// but row IDs are 1-based; compensate.
		$rowId = (int) $request->getUserVar('rowId')-1;
		if (!isset($plugins[$rowId])) fatalError('Invalid row ID!');
		return $plugins[$rowId];
	}
}


