<?php
/**
 * @file classes/security/authorization/internal/SubmissionFileStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
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
	 * @param $fileIdAndRevision string This policy will try to
	 * get the submission file from this data.
	 * @param $fileStage int SUBMISSION_FILE_...
	 * @param $viewable boolean Whether the file has to be viewable
	 */
	function __construct($request, $fileIdAndRevision, $fileStage, $viewable = false) {
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

		// Get the submission file.
		$submissionFile = $this->getSubmissionFile($request);
		if (!is_a($submissionFile, 'SubmissionFile')) return AUTHORIZATION_DENY;

		// Make sure that it's in the required stage
		if ($submissionFile->getFileStage() != $this->_fileStage) return AUTHORIZATION_DENY;

		if ($this->_viewable) {
			// Make sure the file is visible. Unless file is included in an open review.
			if (!$submissionFile->getViewable()){
				if ($submissionFile->getAssocType() === ASSOC_TYPE_REVIEW_ASSIGNMENT){
					$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
					$reviewAssignment = $reviewAssignmentDao->getById((int) $submissionFile->getAssocId());
					if ($reviewAssignment->getReviewMethod() != SUBMISSION_REVIEW_METHOD_OPEN){
						return AUTHORIZATION_DENY;
					}
				}
				else{
					return AUTHORIZATION_DENY;
				}
			}
		}

		// Made it through -- permit access.
		return AUTHORIZATION_PERMIT;
	}
}


