<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileAssignedAuditorAccessPolicy.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileAssignedAuditorAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the current user is an assigned
 * 	auditor of the file.
 *
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileAssignedAuditorAccessPolicy extends SubmissionFileBaseAccessPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function SubmissionFileAssignedAuditorAccessPolicy($request, $fileIdAndRevision = null) {
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

		// Get the user
		$user = $request->getUser();
		if (!is_a($user, 'PKPUser')) return AUTHORIZATION_DENY;

		// Get the submission file
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$signoffsFactory =& $signoffDao->getAllByAssocType(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(), null, $user->getId());

		if ($signoffsFactory->wasEmpty()) {
			return AUTHORIZATION_DENY;
		} else {
			return AUTHORIZATION_PERMIT;
		}
	}
}

?>
