<?php

/**
 * @file controllers/grid/admin/mergeUsers/MergeUsersGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MergeUsersGridHandler
 * @ingroup controllers_grid_admin_mergeUsers
 *
 * @brief Handle merge users grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.admin.mergeUsers.MergeUsersGridRow');

class MergeUsersGridHandler extends GridHandler {
	/** integer user id for the user to remove */
	var $_oldUserId;

	/**
	 * Constructor
	 */
	function MergeUsersGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(array(
			ROLE_ID_SITE_ADMIN),
			array('fetchGrid', 'fetchRow', 'mergeUser')
		);
	}


	//
	// Implement template methods from PKPHandler.
	//
	/**
         * @copydoc PKPHandler::authorize()
         * @param $request PKPRequest
         * @param $args array
         * @param $roleAssignments array
         */
        function authorize($request, &$args, $roleAssignments) {
                import('lib.pkp.classes.security.authorization.PkpContextAccessPolicy');
                $this->addPolicy(new PkpContextAccessPolicy($request, $roleAssignments));
                return parent::authorize($request, $args, $roleAssignments);
        }

	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		// Load user-related translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_APP_ADMIN,
			LOCALE_COMPONENT_PKP_ADMIN,
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_APP_COMMON
		);

		// Basic grid configuration.
		$this->setTitle('admin.mergeUsers');

		//
		// Grid columns.
		//
		import('lib.pkp.controllers.grid.admin.mergeUsers.MergeUsersGridCellProvider');
		$mergerUsersGridCellProvider = new MergeUsersGridCellProvider();

		// username
		$this->addColumn(
			new GridColumn(
				'username',
				'user.username',
				null,
				'controllers/grid/gridCell.tpl',
				$mergerUsersGridCellProvider
			)
		);

		// User's real name.
		$this->addColumn(
			new GridColumn(
				'name',
				'user.name',
				null,
				'controllers/grid/gridCell.tpl',
				$mergerUsersGridCellProvider
			)
		);

		// User's email address.
		$this->addColumn(
			new GridColumn(
				'email',
				'user.email',
				null,
				'controllers/grid/gridCell.tpl',
				$mergerUsersGridCellProvider
			)
		);

	}


	//
	// Implement methods from GridHandler.
	//
	/**
	 * @copydoc GridHandler::getRowInstance()
	 * @return UserGridRow
	 */
	function getRowInstance() {
		return new MergeUsersGridRow($this->_oldUserId);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 * @param $request PKPRequest
	 * @return array Grid data.
	 */
	function loadData($request, $filter) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$userDao = DAORegistry::getDAO('UserDAO');
		$roleDao = DAORegistry::getDAO('RoleDAO');

		if ($filter['roleSymbolic'] != 'all' && String::regexp_match_get('/^(\w+)$/', $filter['roleSymbolic'], $matches)) {
			$roleId = $roleDao->getRoleIdFromPath($matches[1]);
			if ($roleId == null) {
				$roleId = 0;
			}
		} else {
			$roleId = 0;
		}

		if ($roleId) {
			$users = $roleDao->getUsersByRoleId($roleId, null, $filter['searchField'], $filter['search'], $filter['searchMatch']);
		} else {
			$users = $userDao->getUsersByField($filter['searchField'], $filter['searchMatch'], $filter['search'], true);
		}

		$userArray = $users->toAssociativeArray();

		// remove the chosen user from the grid, if one has been picked.
		if ($filter['oldUserId']) {
			if (array_key_exists($filter['oldUserId'], $userArray)) {
				unset($userArray[$filter['oldUserId']]);
			}
		}

		return $userArray;
	}

	/**
	 * @copydoc GridHandler::getFilterForm()
	 * @return string Filter template.
	 */
	function getFilterForm() {
		return 'controllers/grid/admin/mergeUsers/mergeUsersGridFilter.tpl';
	}

	/**
	 * @copydoc GridHandler::renderFilter()
	 */
	function renderFilter($request) {

		$fieldOptions = array(
				USER_FIELD_FIRSTNAME => 'user.firstName',
				USER_FIELD_LASTNAME => 'user.lastName',
				USER_FIELD_USERNAME => 'user.username',
				USER_FIELD_EMAIL => 'user.email',
				USER_FIELD_INTERESTS => 'user.interests',
		);

		$roleSymbolicOptions = array(
				'all' => 'admin.mergeUsers.allUsers',
				'manager' => 'user.role.managers',
				'seriesEditor' => 'user.role.seriesEditors',
				'copyeditor' => 'user.role.copyeditors',
				'proofreader' => 'user.role.proofreaders',
				'reviewer' => 'user.role.reviewers',
				'author' => 'user.role.authors',
				'reader' => 'user.role.readers',
		);

		$searchMatchOptions = array(
				'contains' => 'form.contains',
				'is' => 'form.is',
		);

		$filterData = array(
				'roleSymbolicOptions' => $roleSymbolicOptions,
				'fieldOptions' => $fieldOptions,
				'searchMatchOptions' => $searchMatchOptions,
				'oldUserId' => $request->getUserVar('oldUserId'),
		);

		return parent::renderFilter($request, $filterData);
	}

	/**
	 * @copydoc GridHandler::getFilterSelectionData()
	 * @return array Filter selection data.
	 */
	function getFilterSelectionData($request) {
		// Get the search terms.
		$searchField = $request->getUserVar('searchField');
		$searchMatch = $request->getUserVar('searchMatch');
		$search = $request->getUserVar('search');
		$roleSymbolic = $request->getUserVar('roleSymbolic') ? $request->getUserVar('roleSymbolic') : 0;
		$oldUserId = $request->getUserVar('oldUserId');

		// stash this since the grid row requires it.
		$this->_oldUserId = $oldUserId;

		return array(
			'searchField' => $searchField,
			'searchMatch' => $searchMatch,
			'search' => $search,
			'roleSymbolic' => $roleSymbolic,
			'oldUserId' => $oldUserId,
		);

	}
}
?>
