<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep3Form.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep3Form
 * @ingroup submission_form
 *
 * @brief Form for Step 3 of author submission: submission metadata
 */

import('lib.pkp.classes.submission.form.SubmissionSubmitForm');

class PKPSubmissionSubmitStep3Form extends SubmissionSubmitForm {

	/** @var SubmissionMetadataFormImplementation */
	var $_metadataFormImplem;

	/**
	 * Constructor.
	 */
	function PKPSubmissionSubmitStep3Form($context, $submission, $metadataFormImplementation) {
		parent::SubmissionSubmitForm($context, $submission, 3);

		$this->_metadataFormImplem = $metadataFormImplementation;
		$this->_metadataFormImplem->addChecks($submission);
	}

	/**
	 * Initialize form data from current submission.
	 */
	function initData() {
		$this->_metadataFormImplem->initData($this->submission);
		return parent::initData();
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		$this->_metadataFormImplem->readInputData();
	}

	/**
	 * Get the names of fields for which data should be localized
	 * @return array
	 */
	function getLocaleFieldNames() {
		$this->_metadataFormImplem->getLocaleFieldNames();
	}

	/**
	 * Save changes to submission.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return int the submission ID
	 */
	function execute($args, $request) {
		// Execute submission metadata related operations.
		$this->_metadataFormImplem->execute($this->submission, $request);

		// Get an updated version of the submission.
		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($this->submissionId);

		// Set other submission data.
		if ($submission->getSubmissionProgress() <= $this->step) {
			$submission->setDateSubmitted(Core::getCurrentDate());
			$submission->stampStatusModified();
			$submission->setSubmissionProgress(0);
		}

		// Save the submission.
		$submissionDao->updateObject($submission);

		// Assign the default stage participants.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');

		// Managerial roles are skipped -- They have access by default and
		//  are assigned for informational purposes only

		// Sub editor roles are skipped -- They are assigned by manager roles
		//  or by other sub editors

		// Assistant roles -- For each assistant role user group assigned to this
		//  stage in setup, iff there is only one user for the group,
		//  automatically assign the user to the stage
		// But skip authors and reviewers, since these are very submission specific
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$submissionStageGroups = $userGroupDao->getUserGroupsByStage($submission->getContextId(), WORKFLOW_STAGE_ID_SUBMISSION, true, true);
		$managerFound = false;
		while ($userGroup = $submissionStageGroups->next()) {
			$users = $userGroupDao->getUsersById($userGroup->getId(), $submission->getContextId());
			if($users->getCount() == 1) {
				$user = $users->next();
				$stageAssignmentDao->build($submission->getId(), $userGroup->getId(), $user->getId());
				if ($userGroup->getRoleId() == ROLE_ID_MANAGER) $managerFound = true;
			}
		}

		import('classes.workflow.EditorDecisionActionsManager');
		$notificationMgr = new NotificationManager();
		$notificationMgr->updateNotification(
			$request,
			EditorDecisionActionsManager::getStageNotifications(),
			null,
			ASSOC_TYPE_SUBMISSION,
			$submission->getId()
		);

		// Reviewer roles -- Do nothing. Reviewers are not included in the stage participant list, they
		// are administered via review assignments.

		// Author roles
		// Assign only the submitter in whatever ROLE_ID_AUTHOR capacity they were assigned previously
		$submitterAssignments = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), null, null, $submission->getUserId());
		while ($assignment = $submitterAssignments->next()) {
			$userGroup = $userGroupDao->getById($assignment->getUserGroupId());
			if ($userGroup->getRoleId() == ROLE_ID_AUTHOR) {
				$stageAssignmentDao->build($submission->getId(), $userGroup->getId(), $assignment->getUserId());
				// Only assign them once, since otherwise we'll one assignment for each previous stage.
				// And as long as they are assigned once, they will get access to their submission.
				break;
			}
		}

		// Send a notification to associated users if an editor needs assigning
		if (!$managerFound) {
			$roleDao = DAORegistry::getDAO('RoleDAO'); /* @var $roleDao RoleDAO */

			// Get the managers.
			$managers = $roleDao->getUsersByRoleId(ROLE_ID_MANAGER, $submission->getContextId());

			$managersArray = $managers->toAssociativeArray();

			$allUserIds = array_keys($managersArray);

			$notificationManager = new NotificationManager();
			foreach ($allUserIds as $userId) {
				$notificationManager->createNotification(
					$request, $userId, NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
					$submission->getContextId(), ASSOC_TYPE_SUBMISSION, $submission->getId()
				);

				// Add TASK notification indicating that a submission is unassigned
				$notificationManager->createNotification(
					$request,
					$userId,
					NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
					$submission->getContextId(),
					ASSOC_TYPE_SUBMISSION,
					$submission->getId(),
					NOTIFICATION_LEVEL_TASK
				);
			}
		}

		$notificationManager->updateNotification(
			$request,
			array(NOTIFICATION_TYPE_APPROVE_SUBMISSION),
			null,
			ASSOC_TYPE_SUBMISSION,
			$submission->getId()
		);

		return $this->submissionId;
	}
}

?>
