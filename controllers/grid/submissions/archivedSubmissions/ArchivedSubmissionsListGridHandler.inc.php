<?php

/**
 * @file controllers/grid/submissions/archivedSubmissions/ArchivedSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
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
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
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
		// Get all contexts that user is enrolled in as manager, series editor
		// reviewer or assistant
		$user = $request->getUser();
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll()->toArray();
		$accessibleRoles = array(
			ROLE_ID_MANAGER,
			ROLE_ID_SUB_EDITOR,
			ROLE_ID_REVIEWER,
			ROLE_ID_ASSISTANT
		);

		$accessibleContexts = array();
		$stageUserId = null;
		$reviewUserId = null;
		foreach ($accessibleRoles as $role) {
			foreach ($contexts as $context) {
				if ($roleDao->userHasRole($context->getId(), $user->getId(), $role)) {
					$accessibleContexts[] = $context->getId();

					if ($role == ROLE_ID_ASSISTANT) {
						$stageUserId = $user->getId();
					} elseif ($role == ROLE_ID_REVIEWER) {
						$reviewUserId = $user->getId();
					}
				}
			}
		}
		$accessibleContexts = array_unique($accessibleContexts);
		if (count($accessibleContexts) == 1) {
			$accessibleContexts = array_pop($accessibleContexts);
		}

		// Fetch all submissions for contexts the user can access. If the user
		// is a reviewer or assistant only show submissions that have been
		// assigned to the user
		$submissionDao = Application::getSubmissionDAO();
		$submissionFactory = $submissionDao->getByStatus(
			array(STATUS_DECLINED, STATUS_PUBLISHED),
			$stageUserId,
			$reviewUserId,
			$accessibleContexts,
			$this->getGridRangeInfo($request, $this->getId())
		);

		return $submissionFactory;
	}
}

?>
