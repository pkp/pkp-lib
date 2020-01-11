<?php
/**
 * @file classes/security/authorization/internal/QueryUserAccessibleWorkflowStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryUserAccessibleWorkflowStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to extend access to queries to assigned reviewers.
 *
 */

import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');

class QueryUserAccessibleWorkflowStageRequiredPolicy extends UserAccessibleWorkflowStageRequiredPolicy {

	//
	// Private helper methods.
	//
	/**
	 * @see AuthorizationPolicy::effect()
	 */
	function effect() {

		$result = parent::effect();
		if ($result === AUTHORIZATION_PERMIT) {
			return $result;
		}

		if (!in_array(ROLE_ID_REVIEWER, $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES))) {
			return $result;
		}

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$reviewAssignments = DAORegistry::getDAO('ReviewAssignmentDAO')->getBySubmissionId($submission->getId());
		foreach ($reviewAssignments as $reviewAssignment) {
			if ($reviewAssignment->getReviewerId() === $this->_request->getUser()->getId()) {
				$accessibleWorkflowStages = (array) $this->getAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
				$accessibleWorkflowStages[WORKFLOW_STAGE_ID_INTERNAL_REVIEW] = array_merge(
					(array) $accessibleWorkflowStages[WORKFLOW_STAGE_ID_INTERNAL_REVIEW],
					[ROLE_ID_REVIEWER]
				);
				$accessibleWorkflowStages[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] = array_merge(
					(array) $accessibleWorkflowStages[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW],
					[ROLE_ID_REVIEWER]
				);
				$this->addAuthorizedContextObject(ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);
				return AUTHORIZATION_PERMIT;
			}
		}

		return $result;
	}
}


