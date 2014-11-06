<?php

/**
 * @file controllers/grid/submissions/assignedSubmissions/AssignedSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AssignedSubmissionsListGridHandler
 * @ingroup controllers_grid_submissions_assignedSubmissions
 *
 * @brief Handle submissions list grid requests (submissions the user is assigned to).
 */

// Import grid base classes.
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridHandler');
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridRow');

// Filter editor
define('FILTER_EDITOR_ALL', 0);
define('FILTER_EDITOR_ME', 1);

class AssignedSubmissionsListGridHandler extends SubmissionsListGridHandler {
	/**
	 * Constructor
	 */
	function AssignedSubmissionsListGridHandler() {
		parent::SubmissionsListGridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow', 'deleteSubmission')
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

		// Set title.
		$this->setTitle('common.queue.long.myAssigned');
	}

	//
	// Implement template methods from SubmissionListGridHandler
	//
	/**
	 * @copydoc SubmissionListGridHandler::getSubmissions()
	 */
	function getSubmissions($request, $userId) {
		$submissionDao = Application::getSubmissionDAO();
		return $submissionDao->getAssigned(
			$userId,
			$this->getGridRangeInfo($request, $this->getId())
		);
	}
}

?>
