<?php

/**
 * @file controllers/grid/settings/roles/UserGroupGridHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGroupGridHandler
 * @ingroup controllers_grid_settings
 *
 * @brief Handle operations for user group management operations.
 */

// Import the base GridHandler.
import('lib.pkp.classes.controllers.grid.CategoryGridHandler');
import('lib.pkp.classes.controllers.grid.DataObjectGridCellProvider');

// import user group grid specific classes
import('lib.pkp.controllers.grid.settings.roles.UserGroupGridCategoryRow');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

class UserGroupGridHandler extends CategoryGridHandler {
	var $_contextId;

	/**
	 * Constructor
	 */
	function UserGroupGridHandler() {
		parent::CategoryGridHandler();

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER),
			array(
				'fetchGrid',
				'fetchCategory',
				'fetchRow',
				'addUserGroup',
				'editUserGroup',
				'updateUserGroup'
			)
		);
	}

	//
	// Overridden methods from PKPHandler.
	//
	/**
	 * @see PKPHandler::authorize()
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
	 * @see PKPHandler::initialize()
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		$context = $request->getContext();
		$this->_contextId = $context->getId();

		// Load user-related translations.
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);

		// Basic grid configuration.
		$this->setTitle('grid.roles.currentRoles');
		$this->setInstructions('settings.roles.gridDescription');

		// Add grid-level actions.
		$router = $request->getRouter();
		$this->addAction(
			new LinkAction(
				'addUserGroup',
				new AjaxModal(
					$router->url($request, null, null, 'addUserGroup'),
					__('grid.roles.add'),
					'modal_add_role'
				),
				__('grid.roles.add'),
				'add_role'
			)
		);

		// Add grid columns.
		$cellProvider = new DataObjectGridCellProvider();
		$cellProvider->setLocale(AppLocale::getLocale());

		// Add array columns to the grid.
		$this->addColumn(new GridColumn(
			'name',
			'settings.roles.roleName',
			null,
			'controllers/grid/gridCell.tpl',
			$cellProvider,
			array('alignment' => COLUMN_ALIGNMENT_LEFT)
		));
		$this->addColumn(new GridColumn(
			'abbrev',
			'settings.roles.roleAbbrev',
			null,
			'controllers/grid/gridCell.tpl',
			$cellProvider
		));
	}

	/**
	 * @see GridHandler::loadData
	 */
	function loadData($request, $filter) {
		$contextId = $this->_getContextId();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		if (is_array($filter) && isset($filter['selectedRoleId']) && $filter['selectedRoleId'] != 0) {
			$userGroups = $userGroupDao->getByRoleId($contextId, $filter['selectedRoleId']);
		} else {
			$userGroups = $userGroupDao->getByContextId($contextId);
		}

		$stages = array();
		while ($userGroup = $userGroups->next()) {
			$userGroupStages = $this->_getAssignedStages($contextId, $userGroup->getId());
			foreach ($userGroupStages as $stageId => $stage) {
				if ($stage != null) {
					$stages[$stageId] = array('id' => $stageId, 'name' => $stage);
				}
			}
		}

		return $stages;
	}

	/**
	 * @see GridHandler::getRowInstance()
	 * @return UserGroupGridRow
	 */
	function getRowInstance() {
		import('lib.pkp.controllers.grid.settings.roles.UserGroupGridRow');
		return new UserGroupGridRow();
	}

	/**
	 * @see CategoryGridHandler::geCategorytRowInstance()
	 * @return UserGroupGridCategoryRow
	 */
	function getCategoryRowInstance() {
		return new UserGroupGridCategoryRow();
	}

	/**
	 * @see CategoryGridHandler::getCategoryData()
	 */
	function getCategoryData(&$stage) {
		// $stage is an associative array, with id and name (locale key) elements
		$stageId = $stage['id'];

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		$assignedGroups = $userGroupDao->getUserGroupsByStage($this->_getContextId(), $stageId);
		return $assignedGroups->toAssociativeArray(); // array of UserGroup objects
	}

	/**
	 * Handle the add user group operation.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function addUserGroup($args, $request) {
		return $this->editUserGroup($args, $request);
	}

	/**
	 * Handle the edit user group operation.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function editUserGroup($args, $request) {
		$userGroupForm = $this->_getUserGroupForm($request);

		$userGroupForm->initData();

		$json = new JSONMessage(true, $userGroupForm->fetch($request));
		return $json->getString();
	}

	/**
	 * Update user group data on database and grid.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function updateUserGroup($args, $request) {
		$userGroupForm = $this->_getUserGroupForm($request);

		$userGroupForm->readInputData();
		if($userGroupForm->validate()) {
			$userGroupForm->execute($request);
			return DAO::getDataChangedEvent();
		} else {
			$json = new JSONMessage(true, $userGroupForm->fetch($request));
			return $json->getString();
		}
	}

	//
	// Private helper methods.
	//

	/**
	 * Get a UserGroupForm instance.
	 * @param $request Request
	 * @return UserGroupForm
	 */
	function _getUserGroupForm($request) {
		// Get the user group Id.
		$userGroupId = (int) $request->getUserVar('userGroupId');

		// Instantiate the files form.
		import('lib.pkp.controllers.grid.settings.roles.form.UserGroupForm');
		$contextId = $this->_getContextId();
		return new UserGroupForm($contextId, $userGroupId);
	}

	/**
	 * Get a list of stages that are assigned to a user group.
	 * @param $id int Context id
	 * @param $id int UserGroup id
	 * @return array Given user group stages assignments.
	 */
	function _getAssignedStages($contextId, $userGroupId) {
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$assignedStages = $userGroupDao->getAssignedStagesByUserGroupId($contextId, $userGroupId);

		$stages = $userGroupDao->getWorkflowStageTranslationKeys();
		foreach($stages as $stageId => $stageTranslationKey) {
			if (!array_key_exists($stageId, $assignedStages)) unset($stages[$stageId]);
		}

		return $stages;
	}

	/**
	 * Get context id.
	 * @return int
	 */
	function _getContextId() {
		return $this->_contextId;
	}
}

?>
