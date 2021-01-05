<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileStageAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileStageAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to ensure that the user can read or write to a particular
 * 	file stage based on their stage assignments. This policy expects submission, user roles
 *  and workflow stage assignments in the authorized context.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class SubmissionFileStageAccessPolicy extends AuthorizationPolicy {
	/** @var int SUBMISSION_FILE_... */
	var $_fileStage;

	/** @var int SUBMISSION_FILE_ACCESS_READ... */
	var $_action;

	/**
	 * Constructor
	 * @param $fileStage int SUBMISSION_FILE_...
	 * @param $action int SUBMISSION_FILE_ACCESS_READ or SUBMISSION_FILE_ACCESS_MODIFY
	 * @param $message string The message to display when authorization is denied
	 */
	function __construct($fileStage, $action, $message) {
		parent::__construct($message);
		$this->_fileStage = $fileStage;
		$this->_action = $action;
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$stageAssignments = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);

		// File stage required
		if (empty($this->_fileStage)) {
			return AUTHORIZATION_DENY;
		}

		// Managers can access file stages when not assigned or when assigned as a manager
		if (empty($stageAssignments)) {
			if (in_array(ROLE_ID_MANAGER, $userRoles)) {
				return AUTHORIZATION_PERMIT;
			}
			return AUTHORIZATION_DENY;
		}

		// Determine the allowed file stages
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$assignedFileStageIds = $submissionFileDao->getAssignedFileStageIds($stageAssignments, $this->_action);

		// Authors may write to the submission files stage if the submission
		// is not yet complete
		if ($this->_fileStage === SUBMISSION_FILE_SUBMISSION && $this->_action === SUBMISSION_FILE_ACCESS_MODIFY) {
			if (!empty($stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION])
					&& count($stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION]) === 1
					&& in_array(ROLE_ID_AUTHOR, $stageAssignments[WORKFLOW_STAGE_ID_SUBMISSION])
					&& $submission->getData('submissionProgress') > 0) {
				$assignedFileStageIds[] = SUBMISSION_FILE_SUBMISSION;
			}
		}

		// Authors may write to the revision files stage if an accept or request revisions
		// decision has been made in the latest round
		if ($this->_fileStage === SUBMISSION_FILE_REVIEW_REVISION && $this->_action === SUBMISSION_FILE_ACCESS_MODIFY) {
			$assignedReviewRoles = array_merge(
				$stageAssignments[WORKFLOW_STAGE_ID_INTERNAL_REVIEW] ?? [],
				$stageAssignments[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] ?? []
			);
			$assignedReviewRoles = array_unique(array_values($assignedReviewRoles));
			if (count($assignedReviewRoles) === 1 && in_array(ROLE_ID_AUTHOR, $assignedReviewRoles)) {
				$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */
				$reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId());
				if ($reviewRound) {
					$editDecisionDao = DAORegistry::getDAO('EditDecisionDAO'); /* @var $editDecisionDao EditDecisionDAO */
					$decisions = $editDecisionDao->getEditorDecisions($submission->getId(), $reviewRound->getStageId(), $reviewRound->getRound());
					if (!empty($decisions)) {
						foreach ($decisions as $decision) {
							if ($decision['decision'] == SUBMISSION_EDITOR_DECISION_ACCEPT
									|| $decision['decision'] == SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS
									|| $decision['decision'] == SUBMISSION_EDITOR_DECISION_NEW_ROUND
									|| $decision['decision'] == SUBMISSION_EDITOR_DECISION_RESUBMIT) {
								$assignedFileStageIds[] = SUBMISSION_FILE_REVIEW_REVISION;
								break;
							}
						}
					}
				}
			}
		}

		if  (in_array($this->_fileStage, $assignedFileStageIds)) {
			$this->addAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_FILE_STAGES, $assignedFileStageIds);
			return AUTHORIZATION_PERMIT;
		}

		return AUTHORIZATION_DENY;
	}
}
