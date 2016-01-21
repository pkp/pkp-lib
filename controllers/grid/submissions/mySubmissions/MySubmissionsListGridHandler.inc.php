<?php

/**
 * @file controllers/grid/submissions/mySubmissions/MySubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class MySubmissionsListGridHandler
 * @ingroup controllers_grid_submissions_mySubmissions
 *
 * @brief Handle author's submissions list grid requests (submissions the user has made).
 */

// Import grid base classes.
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridHandler');
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridRow');

class MySubmissionsListGridHandler extends SubmissionsListGridHandler {
	/**
	 * Constructor
	 */
	function MySubmissionsListGridHandler() {
		parent::SubmissionsListGridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRows', 'fetchRow', 'deleteSubmission')
		);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);
		
		$this->setTitle('submission.mySubmissions');
	}

	
	//
	// Implement methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		list($search, $column, $stageId) = $this->getFilterValues($filter);

		$submissionDao = Application::getSubmissionDAO();
		return $submissionDao->getUnpublishedByUserId(
			$request->getUser()->getId(),
			$request->getContext()->getId(),
			$search,
			$stageId,
			$this->getGridRangeInfo($request, $this->getId())
		);
	}

	
	//
	// Extend methods from SubmissionListGridHandler
	//
	/**
	 * @copyDoc SubmissionListGridHandler::getFilterColumns()
	 */
	function getFilterColumns() {
		$columns = parent::getFilterColumns();
		unset($columns['author']);

		return $columns;
	}
}

?>
