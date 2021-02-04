<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileAuthorEditorPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAuthorEditorPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to ensure that an editor is denied access to
 *  anonymous review files when they are also assigned to the submission as an
 *  author.
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileAuthorEditorPolicy extends SubmissionFileBaseAccessPolicy {
	/**
	 * @copydoc AuthorizationPolicy::effect()
	 */
	public function effect() {
		$request = $this->getRequest();

		// Get the submission file.
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		// Allow if this is not a file submitted with a review
		if ($submissionFile->getFileStage() != SUBMISSION_FILE_REVIEW_ATTACHMENT) return AUTHORIZATION_PERMIT;

		// Deny if the user is assigned as an author to any stage, and this file is
		// attached to an anonymous review
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
		foreach ($userRoles as $stageRoles) {
			if (in_array(ROLE_ID_AUTHOR, $stageRoles)) {
				$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
				$reviewAssignment = $reviewAssignmentDao->getById((int) $submissionFile->getData('assocId'));
				if ($reviewAssignment && $reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_OPEN){
					return AUTHORIZATION_DENY;
				}
				break;
			}
		}

		return AUTHORIZATION_PERMIT;
	}
}
