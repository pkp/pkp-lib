<?php

/**
 * @file pages/admin/AdminPeopleHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AdminPeopleHandler
 * @ingroup pages_admin
 *
 * @brief Handle requests for people management functions.
 */

import('lib.pkp.pages.admin.AdminHandler');

class AdminPeopleHandler extends AdminHandler {
	/**
	 * Constructor
	 */
	function AdminPeopleHandler() {
		parent::AdminHandler();

		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN),
			array('mergeUsers')
		);
	}

	/**
	 * Allow the Site Administrator to merge user accounts, including attributed submissions etc.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function mergeUsers($args, $request) {
		$this->setupTemplate($request, true);

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$roleDao = DAORegistry::getDAO('RoleDAO');

		$templateMgr = TemplateManager::getManager($request);

		// retrieve the grid filter request variables so they can be
		// passed along to the grid via the template.
		$searchField = $request->getUserVar('searchField');
		$searchMatch = $request->getUserVar('searchMatch');
		$search = $request->getUserVar('search');
		$roleSymbolic = $request->getUserVar('roleSymbolic');
		$oldUserId = $request->getUserVar('oldUserId');
		$newUserId = $request->getUserVar('newUserId');

		$templateMgr->assign(
			'gridParams',
			array(
				'searchField' => $searchField,
				'searchMatch' => $searchMatch,
				'search' => $search,
				'roleSymbolic' => $roleSymbolic,
				'oldUserId' => $oldUserId,
			)
		);

		if (!empty($oldUserId) && !empty($newUserId)) {
			import('classes.user.UserAction');
			$userAction = new UserAction();
			$userAction->mergeUsers($oldUserId, $newUserId);
			$request->redirect(null, 'admin', 'mergeUsers');
		}

		$templateMgr->display('admin/selectMergeUser.tpl');
	}

}

?>
