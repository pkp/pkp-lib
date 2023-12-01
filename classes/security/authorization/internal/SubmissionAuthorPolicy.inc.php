<?php
/**
 * @file classes/security/authorization/internal/SubmissionAuthorPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAuthorPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a submission based on authorship.
 *
 * NB: This policy expects a previously authorized submission in the
 * authorization context.
 */

import('lib.pkp.classes.security.authorization.AuthorizationPolicy');
import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');

class SubmissionAuthorPolicy extends AuthorizationPolicy {
	/** @var PKPRequest */
	var $_request;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function __construct($request) {
		parent::__construct('user.authorization.submissionAuthor');
		$this->_request = $request;
	}

	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Get the user
		$user = $this->_request->getUser();
		if (!is_a($user, 'User')) return AUTHORIZATION_DENY;

		// Get the submission
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		if (!is_a($submission, 'Submission')) return AUTHORIZATION_DENY;

		$context = $this->_request->getContext();

		// Check authorship of the submission. Any ROLE_ID_AUTHOR assignment will do.
		$accessibleWorkflowStages = Services::get('user')->getAccessibleWorkflowStages(
			$user->getId(),
			$context->getId(),
			$submission,
			$this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
		);

		if (empty($accessibleWorkflowStages)) {
			return AUTHORIZATION_DENY;
		}

		foreach ($accessibleWorkflowStages as $roles) {
			if (in_array(ROLE_ID_AUTHOR, $roles)) {
				$this->addAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);
				return AUTHORIZATION_PERMIT;
			}
		}

		return AUTHORIZATION_DENY;
	}
}


