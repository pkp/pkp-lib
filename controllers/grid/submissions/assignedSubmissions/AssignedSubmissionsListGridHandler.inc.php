<?php

/**
 * @file controllers/grid/submissions/assignedSubmissions/AssignedSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
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

		// Set title.
		$this->setTitle('common.queue.long.myAssigned');
	}


	//
	// Implement methods from GridHandler 
	//
	/**
	 * @copydoc GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		$user = $request->getUser();
		$userId = $user->getId();

		$submissionDao = Application::getSubmissionDAO();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$authorDao = DAORegistry::getDAO('AuthorDAO');

		list($search, $column, $stageId) = $this->getFilterValues($filter);
		$title = $author = null;
		if ($column == 'title') {
			$title = $search;
		} else {
			$author = $search;
		}
	
		$rangeInfo = $this->getGridRangeInfo($request, $this->getId());
		$context = $request->getContext();

		return $submissionDao->getAssignedToUser($userId, $context->getId(), $title, $author, $stageId, $rangeInfo);
	}
}

?>
