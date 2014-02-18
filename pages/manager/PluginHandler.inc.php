<?php

/**
 * @file pages/manager/PluginHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PluginHandler
 * @ingroup pages_manager
 *
 * @brief Handle requests for plugin management functions.
 */

import('pages.manager.ManagerHandler');

class PluginHandler extends ManagerHandler {
	/**
	 * Constructor
	 */
	function PluginHandler() {
		parent::ManagerHandler();
		$this->addRoleAssignment(ROLE_ID_MANAGER,
			array('plugin')
		);
	}

	/**
	 * Perform plugin-specific management functions.
	 * @param $args array
	 * @param $request object
	 */
	function plugin($args, $request) {
		$category = array_shift($args);
		$plugin = array_shift($args);
		$verb = array_shift($args);

		$this->setupTemplate($request);

		$plugins = PluginRegistry::loadCategory($category);
		$message = null;
		if (!isset($plugins[$plugin]) || !$plugins[$plugin]->manage($verb, $args, $message)) {
			if ($message) {
				$notificationManager = new NotificationManager();
				$user = $request->getUser();
				$notificationManager->createTrivialNotification($user->getId(), $message);
			}
			$request->redirect(null, null, 'index', array($category));
		}
	}
}

?>
