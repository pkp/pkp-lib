<?php
/**
 * @file classes/security/authorization/internal/PublicationIsSubmissionPolicy.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationIsSubmissionPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to ensure the authorized publication is related to the authorized submission.
 *
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class PublicationIsSubmissionPolicy extends AuthorizationPolicy {
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publication = $this->getAuthorizedContextObject(ASSOC_TYPE_PUBLICATION);

		if ($submission && $publication && $submission->getId() === $publication->getData('submissionId')) {
			return AUTHORIZATION_PERMIT;
		}

		return AUTHORIZATION_DENY;
	}
}


