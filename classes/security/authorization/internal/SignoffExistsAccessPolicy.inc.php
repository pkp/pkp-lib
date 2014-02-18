<?php
/**
 * @file classes/security/authorization/internal/SignoffExistsAccessPolicy.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffExistsAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a signoff for the current context.
 *
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');

class SignoffExistsAccessPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function SignoffExistsAccessPolicy($request) {
		parent::AuthorizationPolicy('user.authorization.submissionSignoff');
		$this->_request =& $request;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Check if the signoff exists
		$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
		$signoff = $signoffDao->getById($this->_request->getUserVar('signoffId'));
		$baseSignoff =& $signoff;

		// Check that the signoff exists
		if (!is_a($signoff, 'Signoff')) return AUTHORIZATION_DENY;

		// Check that we know what the current context is
		$context = $this->_request->getContext();
		if (!is_a($context, 'Context')) return AUTHORIZATION_DENY;

		// Ensure that the signoff belongs to the current context
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionDao = Application::getSubmissionDAO();
		while (true) switch ($signoff->getAssocType()) {
			case ASSOC_TYPE_SIGNOFF:
				// This signoff is attached to another signoff.
				// We need to determine that the attached
				// signoff belongs to the current context.
				$newSignoff = $signoffDao->getById($signoff->getAssocId());
				if (!is_a($newSignoff, 'Signoff')) return AUTHORIZATION_DENY;

				// Flip the reference so that the new object
				// gets authorized.
				unset($signoff);
				$signoff =& $newSignoff;
				unset($newSignoff);
				break;
			case ASSOC_TYPE_SUBMISSION_FILE:
				// Get the submission file
				$submissionFile =& $submissionFileDao->getLatestRevision($signoff->getAssocId());
				if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

				// Get the submission
				$submission = $submissionDao->getById($submissionFile->getSubmissionId(), $context->getId());
				if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

				// Integrity checks OK. Permit.
				$this->addAuthorizedContextObject(ASSOC_TYPE_SIGNOFF, $baseSignoff);
				return AUTHORIZATION_PERMIT;
			case ASSOC_TYPE_SUBMISSION:
				$submission = $submissionDao->getById($signoff->getAssocId());
				if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

				if ($submission->getContextId() != $context->getId()) return AUTHORIZATION_DENY;

				// Checks out OK. Permit.
				$this->addAuthorizedContextObject(ASSOC_TYPE_SIGNOFF, $baseSignoff);
				return AUTHORIZATION_PERMIT;
			default: return AUTHORIZATION_DENY;
		}
	}
}

?>
