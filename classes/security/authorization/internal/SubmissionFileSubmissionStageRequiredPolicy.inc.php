<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileSubmissionStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileSubmissionStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to ensure that we have a file at submission stage.
 *
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileSubmissionStageRequiredPolicy extends SubmissionFileBaseAccessPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function SubmissionFileSubmissionStageRequiredPolicy($request, $fileIdAndRevision = null) {
		parent::SubmissionFileBaseAccessPolicy($request, $fileIdAndRevision);
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request = $this->getRequest();

		// Get the submission file.
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		// Make sure that it's in the submission stage
		if ($submissionFile->getFileStage() != SUBMISSION_FILE_SUBMISSION) return AUTHORIZATION_DENY;

		// Make sure the file belongs to the submission in request.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;
		if ($submission->getId() != $submissionFile->getSubmissionId()) return AUTHORIZATION_DENY;

		// Made it through -- permit access.
		return AUTHORIZATION_PERMIT;
	}
}

?>
