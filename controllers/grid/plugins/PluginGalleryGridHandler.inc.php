<?php

/**
 * @file controllers/grid/settings/pluginGallery/PluginGalleryGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginGalleryGridHandler
 * @ingroup controllers_grid_settings_pluginGallery
 *
 * @brief Handle review form grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');

import('lib.pkp.controllers.grid.plugins.PluginGalleryGridRow');

class PluginGalleryGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function PluginGalleryGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN),
			array('fetchGrid', 'fetchRow', 'viewPlugin')
		);
		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN),
			array('installPlugin')
		);
	}


	//
	// Implement template methods from PKPHandler.
	//
	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize($request) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_PKP_GRID);

		// Basic grid configuration.
		$this->setTitle('manager.plugins.pluginGallery');

		// Grid actions.
		$router = $request->getRouter();

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
				'controllers/grid/gridCell.tpl',
				$pluginGalleryGridCellProvider
			)
		);

		// Description.
		$this->addColumn(
			new GridColumn(
				'summary',
				'common.description',
				null,
				'controllers/grid/gridCell.tpl',
				$pluginGalleryGridCellProvider,
				array('width' => 70, 'alignment' => COLUMN_ALIGNMENT_LEFT)
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
	 * @see GridHandler::getRowInstance()
	 * @return UserGridRow
	 */
	function getRowInstance() {
		return new PluginGalleryGridRow();
	}

	/**
	 * @see GridHandler::loadData()
	 * @param $request PKPRequest
	 * @return array Grid data.
	 */
	function loadData($request) {
		// Get all plugins.
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
		return $pluginGalleryDao->getNewestCompatible(Application::getApplication());
	}

	//
	// Public operations
	//
	/**
	 * View a plugin's details
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string
	 */
	function viewPlugin($args, $request) {
		$plugin = $this->_getSpecifiedPlugin($request);

		// Display plugin information
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('plugin', $plugin);

		// Get currently installed version, if any.
		$installedVersion = $plugin->getInstalledVersion(Application::getApplication());
		$installActionKey=null;
		if ($installedVersion) {
			if ($installedVersion->compare($plugin->getVersion())>0) {
				$statusKey = 'manager.plugins.installedVersionNewer';
				$statusClass = 'newer';
			} elseif ($installedVersion->compare($plugin->getVersion())<0) {
				$statusKey = 'manager.plugins.installedVersionOlder';
				$statusClass = 'older';
				$installActionKey='common.upgrade';
			} else {
				$statusKey = 'manager.plugins.installedVersionNewest';
				$statusClass = 'newest';
			}
		} else {
			$statusKey = 'manager.plugins.noInstalledVersion';
			$statusClass = 'notinstalled';
			$installActionKey='grid.action.install';
		}
		$templateMgr->assign('statusKey', $statusKey);
		$templateMgr->assign('statusClass', $statusClass);

		$router = $request->getRouter();
		if (Validation::isSiteAdmin() && $installActionKey) $templateMgr->assign('installAction', new LinkAction(
			'installPlugin',
			new RemoteActionConfirmationModal(
				__('manager.plugins.installConfirm'),
				__($installActionKey),
				$router->url($request, null, null, 'installPlugin', null, array('rowId' => $rowId)),
				'modal_information'
			),
			__($installActionKey),
			null
		));
		$json = new JSONMessage(true, $templateMgr->fetch('controllers/grid/plugins/viewPlugin.tpl'));
		return $json->getString();
	}

	/**
	 * Install or upgrade a plugin
	 */
	function installPlugin($args, $request) {
		$plugin = $this->_getSpecifiedPlugin($request);
		print_r($plugin);exit();
	}

	/**
	 * Get the specified plugin.
	 * @param $request PKPRequest
	 * @return GalleryPlugin
	 */
	function _getSpecifiedPlugin($request) {
		// Get all plugins.
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
		$plugins = $pluginGalleryDao->getNewestCompatible(Application::getApplication());

		// Get specified plugin
		$rowId = (int) $request->getUserVar('rowId');
		if (!isset($plugins[$rowId])) fatalError('Invalid row ID!');
		return $plugins[$rowId];
	}
}

?>
