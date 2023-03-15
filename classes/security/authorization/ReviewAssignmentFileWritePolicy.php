<?php
/**
 * @file classes/security/authorization/ReviewAssignmentFileWritePolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentFileWritePolicy
 * @ingroup security_authorization_internal
 *
 * @brief Authorize access to add, edit and delete reviewer attachments. This policy
 *   expects review round, submission, assigned workflow stages and user roles to be
 *   in the authorized context.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;

class ReviewAssignmentFileWritePolicy extends AuthorizationPolicy
{
    /** @var Request */
    private $_request;

    /** @var int */
    private $_reviewAssignmentId;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param int $reviewAssignmentId
     */
    public function __construct($request, $reviewAssignmentId)
    {
        parent::__construct('user.authorization.unauthorizedReviewAssignment');
        $this->_request = $request;
        $this->_reviewAssignmentId = $reviewAssignmentId;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        if (!$this->_reviewAssignmentId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $reviewRound = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ROUND);
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $assignedStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        if (!$reviewRound || !$submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        /** @var ReviewAssignmentDAO */
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
        $reviewAssignment = $reviewAssignmentDao->getById($this->_reviewAssignmentId);

        if (!($reviewAssignment instanceof \PKP\submission\reviewAssignment\ReviewAssignment)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Review assignment, review round and submission must match
        if ($reviewAssignment->getReviewRoundId() != $reviewRound->getId()
                || $reviewRound->getSubmissionId() != $submission->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Managers can write review attachments when they are not assigned to a submission
        if (empty($stageAssignments) && count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            $this->addAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Managers, editors and assistants can write review attachments when they are assigned
        // to the correct stage.
        if (!empty($assignedStages[$reviewRound->getStageId()])) {
            $allowedRoles = [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
            if (!empty(array_intersect($allowedRoles, $assignedStages[$reviewRound->getStageId()]))) {
                $this->addAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        // Reviewers can write review attachments to their own review assignments,
        // if the assignment is not yet complete, cancelled or declined.
        if ($reviewAssignment->getReviewerId() == $this->_request->getUser()->getId()) {
            $notAllowedStatuses = [ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_DECLINED, ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE, ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_THANKED, ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_CANCELLED];
            if (!in_array($reviewAssignment->getStatus(), $notAllowedStatuses)) {
                $this->addAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\ReviewAssignmentFileWritePolicy', '\ReviewAssignmentFileWritePolicy');
}
