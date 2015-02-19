<?php

/**
 * @file controllers/grid/submissions/archivedSubmissions/ArchivedSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ArchivedSubmissionsListGridHandler
 * @ingroup controllers_grid_submissions_archivedSubmissions
 *
 * @brief Handle archived submissions list grid requests.
 */

// Import grid base classes.
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridHandler');
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridRow');

// Filter editor
define('FILTER_EDITOR_ALL', 0);
define('FILTER_EDITOR_ME', 1);

class ArchivedSubmissionsListGridHandler extends SubmissionsListGridHandler {
	/**
	 * Constructor
	 */
	function ArchivedSubmissionsListGridHandler() {
		parent::SubmissionsListGridHandler();
		$this->addRoleAssignment(
			array(ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
			array('fetchGrid', 'fetchRow', 'deleteSubmission')
		);
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
			array('deleteSubmission')
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
		$this->setTitle('common.queue.long.submissionsArchived');

		// Add editor specific locale component.
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
	}


	//
	// Implement template methods from SubmissionListGridHandler
	//
	/**
	 * @copydoc SubmissionListGridHandler::getSubmissions()
	 */
	function getSubmissions($request) {
		$context = $request->getContext();
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$canSeeAllSubmissions = in_array(ROLE_ID_MANAGER, $userRoles);

		$submissionDao = Application::getSubmissionDAO();
		return $submissionDao->getByStatus(
			array(STATUS_DECLINED, STATUS_PUBLISHED),
			$canSeeAllSubmissions?null:$user->getId(),
			$context->getId(),
			$this->getGridRangeInfo($request, $this->getId())
		);
	}
}

?>
