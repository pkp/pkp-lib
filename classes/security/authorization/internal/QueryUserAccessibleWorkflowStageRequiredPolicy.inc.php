<?php
/**
 * @file classes/security/authorization/internal/QueryUserAccessibleWorkflowStageRequiredPolicy.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class QueryUserAccessibleWorkflowStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to deny access to query related contents if a review assignment is not found.
 *
 */

import('lib.pkp.classes.security.authorization.internal.UserAccessibleWorkflowStageRequiredPolicy');

class QueryUserAccessibleWorkflowStageRequiredPolicy extends UserAccessibleWorkflowStageRequiredPolicy {

	//
	// Private helper methods.
	//
	/**
	 * Check for review assignments that give access to the passed workflow stage related queries
	 * @param int $userId
	 * @param int $contextId
	 * @param Submission $submission
	 * @param int $stageId
	 * @return array
	 */
	function _getAccessibleStageRoles($userId, $contextId, &$submission, $stageId) {
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);

		$accessibleStageRoles = array();
		foreach ($userRoles as $roleId) {
			switch ($roleId) {
				case ROLE_ID_REVIEWER:
					// Review assignment must exist in the given submission
					$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId());
					foreach ($reviewAssignments as $reviewAssignment) {
						if($reviewAssignment->getReviewerId() == $userId) {
							$accessibleStageRoles[] = $roleId;
						}
					}
					break;
				default:
					break;
			}
		}
		
		return array_merge($accessibleStageRoles, parent::_getAccessibleStageRoles($userId, $contextId, $submission, $stageId));
	}
}


