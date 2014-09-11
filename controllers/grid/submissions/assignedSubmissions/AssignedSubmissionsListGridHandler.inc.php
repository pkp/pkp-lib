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
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$authorDao = DAORegistry::getDAO('AuthorDAO');

		// Get submissions the user is a stage participant for
		$signoffs = $signoffDao->getByUserId($userId);

		$authorUserGroupIds = $userGroupDao->getUserGroupIdsByRoleId(ROLE_ID_AUTHOR);

		$data = array();

		// get signoffs and stage assignments
		$stageAssignments = $stageAssignmentDao->getByUserId($userId);
		while($stageAssignment = $stageAssignments->next()) {
			$submission = $submissionDao->getAssignedById($stageAssignment->getSubmissionId(), $userId);
			if (!$submission) continue;

			$submissionId = $submission->getId();
			$data[$submissionId] = $submission;
		}

		while($signoff = $signoffs->next()) {
			// If it is a submission signoff (and not, say, a file signoff) and
			// If this is an author signoff, do not include (it will be in the 'my submissions' grid)
			if( $signoff->getAssocType() == ASSOC_TYPE_SUBMISSION &&
				!in_array($signoff->getUserGroupId(), $authorUserGroupIds)) {
				$submission = $submissionDao->getById($signoff->getAssocId());
				$submissionId = $submission->getId();
				if ($submission->getStatus() != STATUS_DECLINED) {
					$data[$submissionId] = $submission;
				}
			}
		}

		// Get submissions the user is reviewing
		$reviewerSubmissionDao = DAORegistry::getDAO('ReviewerSubmissionDAO'); /* @var $reviewerSubmissionDao ReviewerSubmissionDAO */
		$reviewerSubmissions = $reviewerSubmissionDao->getReviewerSubmissionsByReviewerId($userId);
		while($reviewerSubmission = $reviewerSubmissions->next()) {
			$submissionId = $reviewerSubmission->getId();
			if (!isset($data[$submissionId])) {
				// Only add if not already provided above --
				// otherwise reviewer workflow link may
				// clobber editorial workflow link
				$data[$submissionId] = $reviewerSubmission;
			}
		}

		return $data;
	}
}

?>
