<?php

/**
 * @file controllers/grid/users/userSelect/UserSelectGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserSelectGridHandler
 * @ingroup controllers_grid_users_userSelect
 *
 * @brief Handle user selector grid requests.
 */

import('lib.pkp.classes.controllers.grid.GridHandler');
import('lib.pkp.controllers.grid.users.userSelect.UserSelectGridCellProvider');

class UserSelectGridHandler extends GridHandler {
	/** @var int User group ID **/
	var $_userGroupId;

	/**
	 * Constructor
	 */
	function UserSelectGridHandler() {
		parent::GridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT),
			array('fetchGrid', 'fetchRows')
		);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = (int)$request->getUserVar('stageId');

		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_APP_EDITOR
		);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$this->_userGroupId = (int) $request->getUserVar('userGroupId');

		$this->setTitle('editor.submission.findAndSelectUser');

		// Columns
		$cellProvider = new UserSelectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'select',
				'',
				null,
				'controllers/grid/users/userSelect/userSelectRadioButton.tpl',
				$cellProvider,
				array('width' => 5)
			)
		);
		$this->addColumn(
			new GridColumn(
				'name',
				'author.users.contributor.name',
				null,
				null,
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
					'width' => 30
				)
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::initFeatures()
	 */
	function initFeatures($request, $args) {
		import('lib.pkp.classes.controllers.grid.feature.InfiniteScrollingFeature');
		import('lib.pkp.classes.controllers.grid.feature.CollapsibleGridFeature');
		return array(new InfiniteScrollingFeature('infiniteScrolling', $this->getItemsNumber()), new CollapsibleGridFeature());
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		list($name) = $this->getFilterValues($filter);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$rangeInfo = $this->getGridRangeInfo($request, $this->getId());
		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
		return $userStageAssignmentDao->filterUsersNotAssignedToStageInUserGroup($submission->getId(), $stageId, $this->_userGroupId, $name, $rangeInfo);
	}

	/**
	 * @copydoc GridHandler::renderFilter()
	 */
	function renderFilter($request, $filterData = array()) {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$allFilterData = array_merge(
			$filterData,
			array(
				'gridId' => $this->getId(),
				'submissionId' => $submission->getId(),
				'stageId' => $stageId,
				'userGroupId' => $this->_userGroupId,
			));
		return parent::renderFilter($request, $allFilterData);
	}

	/**
	 * @copydoc GridHandler::getFilterSelectionData()
	 */
	function getFilterSelectionData($request) {
		$name = (string) $request->getUserVar('name');
		return array(
			'name' => $name,
		);
	}

	/**
	 * @copydoc GridHandler::getFilterForm()
	 */
	protected function getFilterForm() {
		return 'controllers/grid/users/userSelect/searchUserFilter.tpl';
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		return array(
			'submissionId' => $submission->getId(),
			'stageId' => $stageId,
			'userGroupId' => $this->_userGroupId,
		);
	}

	/**
	 * Determine whether a filter form should be collapsible.
	 * @return boolean
	 */
	protected function isFilterFormCollapsible() {
		return false;
	}

	/**
	 * Define how many items this grid will start loading.
	 * @return int
	 */
	protected function getItemsNumber() {
		return 5;
	}

	/**
	 * Process filter values, assigning default ones if
	 * none was set.
	 * @return array
	 */
	protected function getFilterValues($filter) {
		if (isset($filter['name']) && $filter['name']) {
			$name = $filter['name'];
		} else {
			$name = null;
		}
		return array($name);
	}

}

?>
