<?php
/**
 * @file classes/security/authorization/internal/PKPSubmissionFileRequestedRevisionRequiredPolicy.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionFileRequestedRevisionRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Base Submission file policy to ensure we have a viewable file that is part of
 * a review round with the requested revision decision.
 *
 */

import('lib.pkp.classes.security.authorization.internal.SubmissionFileBaseAccessPolicy');

class PKPSubmissionFileRequestedRevisionRequiredPolicy extends SubmissionFileBaseAccessPolicy {
	/**
	 * Constructor
	 * @param $request PKPRequest
	 */
	function PKPSubmissionFileRequestedRevisionRequiredPolicy($request, $fileIdAndRevision = null) {
		parent::SubmissionFileBaseAccessPolicy($request, $fileIdAndRevision);
	}


	//
	// Implement template methods from AuthorizationPolicy
	// Note:  This class is subclassed in each Application, so that Policies have the opportunity to add
	// constraints to the effect() method.  See e.g. SubmissionFileRequestedRevisionRequiredPolicy.inc.php in OMP.
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {
		$request = $this->getRequest();
		$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /* @var $reviewRoundDao ReviewRoundDAO */

		// Get the monograph file.
		$monographFile = $this->getSubmissionFile($request);
		if (!is_a($monographFile, 'MonographFile')) return AUTHORIZATION_DENY;

		// Make sure the file belongs to the monograph in request.
		$monograph =& $this->getAuthorizedContextObject(ASSOC_TYPE_MONOGRAPH);
		if (!is_a($monograph, 'Monograph')) return AUTHORIZATION_DENY;
		if ($monograph->getId() != $monographFile->getSubmissionId()) return AUTHORIZATION_DENY;

		// Make sure the file is part of a review round
		// with a requested revision decision.
		$reviewRound =& $reviewRoundDao->getBySubmissionFileId($monographFile->getFileId());
		if (!is_a($reviewRound, 'ReviewRound')) return AUTHORIZATION_DENY;
		import('classes.workflow.EditorDecisionActionsManager');
		if (!EditorDecisionActionsManager::getEditorTakenActionInReviewRound($reviewRound, array(SUBMISSION_EDITOR_DECISION_PENDING_REVISIONS))) {
			return AUTHORIZATION_DENY;
		}

		// Make sure that it's in the review stage.
		$reviewRound =& $reviewRoundDao->getBySubmissionFileId($monographFile->getFileId());
		if (!is_a($reviewRound, 'ReviewRound')) return AUTHORIZATION_DENY;

		// Make sure review round stage is the same of the current stage in request.
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		if ($reviewRound->getStageId() != $stageId) return AUTHORIZATION_DENY;

		// Made it through -- permit access.
		return AUTHORIZATION_PERMIT;
	}
}

?>
