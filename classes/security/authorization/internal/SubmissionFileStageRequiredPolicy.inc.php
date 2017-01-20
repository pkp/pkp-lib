<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Submission file policy to ensure that we have a file at a required stage.
 *
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class SubmissionFileStageRequiredPolicy extends SubmissionFileBaseAccessPolicy {
	/** @var int SUBMISSION_FILE_... */
	var $_fileStage;

	/** @var boolean Whether the file has to be viewable */
	var $_viewable;

	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function __construct($request, $fileIdAndRevision = null, $fileStage = null, $viewable = false) {
		parent::__construct($request, $fileIdAndRevision);
		$this->_fileStage = $fileStage;
		$this->_viewable = $viewable;
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

		// Get the submission file.
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		// Make sure that it's in the required stage
		if ($submissionFile->getFileStage() != $this->_fileStage) return AUTHORIZATION_DENY;

		if ($this->_viewable) {
			// Make sure the file is visible.
			if (!$submissionFile->getViewable()) return AUTHORIZATION_DENY;
		}

		// Made it through -- permit access.
		return AUTHORIZATION_PERMIT;
	}
}

?>
