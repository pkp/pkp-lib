<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileMatchesWorkflowStageIdPolicy.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileMatchesWorkflowStageIdPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to check if the file belongs to the specified workflow stage ID
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileMatchesWorkflowStageIdPolicy extends SubmissionFileBaseAccessPolicy {
	/** @var int|null Workflow stage ID (WORKFLOW_STAGE_ID_...) */
	protected $_stageId = null;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 * @param $stageId int Workflow stage ID (WORKFLOW_STAGE_ID_...)
	 */
	function __construct($request, $submissionFileId = null, $stageId = null) {
		parent::__construct($request, $submissionFileId);
		$this->_stageId = (int) $stageId;
	}


	//
	// Implement template methods from AuthorizationPolicy
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		// Get the submission file
		$request = $this->getRequest();
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		$workflowStageId = Services::get('submissionFile')->getWorkflowStageId($submissionFile);

		// Check if the submission file belongs to the specified workflow stage.
		if ($workflowStageId != $this->_stageId) return AUTHORIZATION_DENY;

		return AUTHORIZATION_PERMIT;
	}
}

