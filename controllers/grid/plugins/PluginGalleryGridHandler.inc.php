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
			array(ROLE_ID_MANAGER),
			array('fetchGrid', 'fetchRow', 'viewPlugin')
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
				'description',
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
		return $pluginGalleryDao->get();
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
		// Get all plugins.
		$pluginGalleryDao = DAORegistry::getDAO('PluginGalleryDAO');
		$plugins = $pluginGalleryDao->get();

		// Get specified plugin
		$rowId = (int) $request->getUserVar('rowId');
		if (!isset($plugins[$rowId])) fatalError('Invalid row ID!');
		$plugin = $plugins[$rowId];

		// Display plugin information
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('plugin', $plugin);
		$json = new JSONMessage(true, $templateMgr->fetch('controllers/grid/plugins/viewPlugin.tpl'));
		return $json->getString();
	}
}

?>
