<?php
/**
 * @file classes/security/authorization/internal/QueryUserAccessibleWorkflowStageRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryUserAccessibleWorkflowStageRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy to extend access to queries to assigned reviewers.
 *
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Role;

class QueryUserAccessibleWorkflowStageRequiredPolicy extends UserAccessibleWorkflowStageRequiredPolicy
{
    //
    // Private helper methods.
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        $result = parent::effect();
        if ($result === AuthorizationPolicy::AUTHORIZATION_PERMIT) {
            return $result;
        }

        if (!in_array(Role::ROLE_ID_REVIEWER, $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES) ?? [])) {
            return $result;
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId());
        foreach ($reviewAssignments as $reviewAssignment) {
            if ($reviewAssignment->getReviewerId() == $this->_request->getUser()->getId()) {
                $accessibleWorkflowStages = (array) $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
                $accessibleWorkflowStages[WORKFLOW_STAGE_ID_INTERNAL_REVIEW] = array_merge(
                    $accessibleWorkflowStages[WORKFLOW_STAGE_ID_INTERNAL_REVIEW] ?? [],
                    [Role::ROLE_ID_REVIEWER]
                );
                $accessibleWorkflowStages[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] = array_merge(
                    $accessibleWorkflowStages[WORKFLOW_STAGE_ID_EXTERNAL_REVIEW] ?? [],
                    [Role::ROLE_ID_REVIEWER]
                );
                $this->addAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return $result;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\QueryUserAccessibleWorkflowStageRequiredPolicy', '\QueryUserAccessibleWorkflowStageRequiredPolicy');
}
