<?php

/**
 * @file controllers/grid/settings/plugins/SettingsPluginGridHandler.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SettingsPluginGridHandler
 * @ingroup controllers_grid_settings_plugins
 *
 * @brief Handle plugins grid requests.
 */

import('controllers.grid.plugins.PluginGridHandler');

class SettingsPluginGridHandler extends PluginGridHandler {
	/**
	 * Constructor
	 */
	function SettingsPluginGridHandler() {
		$roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_PRESS_MANAGER);

		$this->addRoleAssignment($roles, array('plugin'));

		parent::PluginGridHandler($roles);
	}


	//
	// Extended methods from PluginGridHandler
	//
	/**
	 * @see PluginGridHandler::loadData()
	 */
	function getCategoryData($categoryDataElement, $filter) {
		$plugins = parent::getCategoryData($categoryDataElement, $filter);

		$pressDao =& DAORegistry::getDAO('PressDAO');
		$presses =& $pressDao->getPresses();
		$singlePress = false;
		if ($presses->getCount() == 1) {
			$singlePress = true;
		}

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		$showSitePlugins = false;
		if ($singlePress && in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
			$showSitePlugins = true;
		}

		if ($showSitePlugins) {
			return $plugins;
		} else {
			$pressLevelPlugins = array();
			foreach ($plugins as $plugin) {
				if (!$plugin->isSitePlugin()) {
					$pressLevelPlugins[$plugin->getName()] = $plugin;
				}
				unset($plugin);
			}
			return $pressLevelPlugins;
		}
	}

	//
	// Overriden template methods.
	//
	/**
	 * @see GridHandler::getRowInstance()
	 */
	function getRowInstance() {
		return parent::getRowInstance(CONTEXT_PRESS);
	}
}

?>
