<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileNotQueryAccessPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileNotQueryAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the requested file is not attached
 *  to a query. This returns AUTHORIZATION_PERMIT for _any_ file that is not
 *  attached to a query note.
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileNotQueryAccessPolicy extends SubmissionFileBaseAccessPolicy {

	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request = $this->getRequest();

		// Get the submission file
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		// Check if it's associated with a note.
		if ($submissionFile->getData('assocType') != ASSOC_TYPE_NOTE) return AUTHORIZATION_PERMIT;

		// Check if that note is associated with a query
		$noteDao = DAORegistry::getDAO('NoteDAO'); /* @var $noteDao NoteDAO */
		$note = $noteDao->getById($submissionFile->getData('assocId'));
		if ($note->getAssocType() != ASSOC_TYPE_QUERY) return AUTHORIZATION_PERMIT;

		return AUTHORIZATION_DENY;
	}
}


