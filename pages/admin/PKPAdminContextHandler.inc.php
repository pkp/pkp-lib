<?php

/**
 * @file pages/admin/PKPAdminContextHandler.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAdminContextHandler
 * @ingroup pages_admin
 *
 * @brief Handle requests for context management in site administration.
 */

import('lib.pkp.pages.admin.AdminHandler');

class PKPAdminContextHandler extends AdminHandler {
	/**
	 * Constructor
	 */
	function PKPAdminContextHandler() {
		parent::AdminHandler();

		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN),
			array('contexts')
		);
	}

	/**
	 * Display a list of the contexts hosted on the site.
	 */
	function contexts($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->display('admin/contexts.tpl');
	}
}

?>
