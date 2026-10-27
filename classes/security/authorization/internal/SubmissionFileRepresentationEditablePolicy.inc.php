<?php

/**
 * @file classes/security/authorization/internal/SubmissionFileRepresentationEditablePolicy.inc.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileRepresentationEditablePolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check that the file of a representation (galley,
 *   publication format), or a file depending on it, may be changed by the current
 *   user. Files that are not attached to a representation are not affected.
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileRepresentationEditablePolicy extends SubmissionFileBaseAccessPolicy {

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request = $this->getRequest();

		$user = $request->getUser();
		if (!is_a($user, 'User')) return AUTHORIZATION_DENY;

		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		// A dependent file (e.g. an image of an HTML galley) follows the file it belongs to
		if ($submissionFile->getData('fileStage') == SUBMISSION_FILE_DEPENDENT
			&& $submissionFile->getData('assocType') == ASSOC_TYPE_SUBMISSION_FILE) {
			$submissionFile = Services::get('submissionFile')->get((int) $submissionFile->getData('assocId'));
			if (!$submissionFile) return AUTHORIZATION_DENY;
		}

		if ($submissionFile->getData('assocType') != ASSOC_TYPE_REPRESENTATION) return AUTHORIZATION_PERMIT;

		$representation = Application::get()->getRepresentationDAO()->getById((int) $submissionFile->getData('assocId'));
		$publication = $representation ? Services::get('publication')->get((int) $representation->getData('publicationId')) : null;
		if (!$publication || $publication->getData('submissionId') != $submissionFile->getData('submissionId')) {
			return AUTHORIZATION_DENY;
		}

		return Services::get('submission')->canEditRepresentations($publication, $user)
			? AUTHORIZATION_PERMIT
			: AUTHORIZATION_DENY;
	}
}
