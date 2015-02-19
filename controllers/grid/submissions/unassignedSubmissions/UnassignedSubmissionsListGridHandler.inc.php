<?php

/**
 * @file controllers/grid/submissions/unassignedSubmissions/UnassignedSubmissionsListGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UnassignedSubmissionsListGridHandler
 * @ingroup controllers_grid_submissions_unassignedSubmissions
 *
 * @brief Handle unassigned submissions list grid requests.
 */

// Import grid base classes.
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridHandler');
import('lib.pkp.controllers.grid.submissions.SubmissionsListGridRow');

// Filter editor
define('FILTER_EDITOR_ALL', 0);
define('FILTER_EDITOR_ME', 1);

class UnassignedSubmissionsListGridHandler extends SubmissionsListGridHandler {
	/**
	 * Constructor
	 */
	function UnassignedSubmissionsListGridHandler() {
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
		$this->setTitle('common.queue.long.submissionsUnassigned');

		// Add editor specific locale component.
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_EDITOR);
	}


	//
	// Implement template methods from SubmissionListGridHandler
	//
	/**
	 * @copydoc SubmissionListGridHandler::getSubmissions()
	 */
	function getSubmissions($request, $userId) {
		$submissionDao = Application::getSubmissionDAO(); /* @var $submissionDao SubmissionDAO */

		// Determine whether this is a Sub Editor or Manager.
		// Managers can access all submissions, Sub Editors
		// only assigned submissions.
		$user = $request->getUser();

		// Get all submissions for all contexts that user is
		// enrolled in as manager or series editor.
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$contextDao = Application::getContextDAO();
		$contexts = $contextDao->getAll();
		$accessibleContexts = array();

		while ($context = $contexts->next()) {
			$isManager = $roleDao->userHasRole($context->getId(), $userId, ROLE_ID_MANAGER);
			$isSubEditor = $roleDao->userHasRole($context->getId(), $userId, ROLE_ID_SUB_EDITOR);

			if (!$isManager && !$isSubEditor) {
				continue;
			}
			$accessibleContexts[] = $context->getId();
		}

		$accessibleSubmissions = array();

		// Don't use range info to retrieve the object, because we do
		// some more filtering below, and that would end up breaking
		// the range info. FIXME: to speed up the process, do all
		// the filtering needed in SQL and use range info here.
		$submissionFactory = $submissionDao->getBySubEditorId(
			$accessibleContexts,
			null,
			false, // do not include STATUS_DECLINED submissions
			false  // include only unpublished submissions
		);

		if (!$submissionFactory->wasEmpty()) {
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
			while ($submission = $submissionFactory->next()) {
				if (!$stageAssignmentDao->editorAssignedToStage($submission->getId())) {
					$accessibleSubmissions[$submission->getId()] = $submission;
				}
			}
		}

		$rangeInfo = $this->getGridRangeInfo($request, $this->getId());
		import('lib.pkp.classes.core.VirtualArrayIterator');
		return VirtualArrayIterator::factory($accessibleSubmissions, $rangeInfo);
	}
}

?>
