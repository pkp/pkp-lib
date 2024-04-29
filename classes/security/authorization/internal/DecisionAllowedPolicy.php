<?php
/**
 * @file classes/security/authorization/internal/DecisionAllowedPolicy.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionAllowedPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Checks whether a user is allowed to take the authorized decision
 *   on the authorized submission. Also relies on authorized roles.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

class DecisionAllowedPolicy extends AuthorizationPolicy
{
    protected ?User $user;

    /**
     * Constructor
     *
     */
    public function __construct(?User $user)
    {
        parent::__construct('editor.submission.workflowDecision.disallowedDecision');
        $this->user = $user;
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        if (!$this->user) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $decisionType = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_DECISION_TYPE);

        // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
        $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$submission->getData('stageId')])
            ->withUserId($this->user->getId())
            ->get();

        if ($stageAssignments->isEmpty()) {
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            $canAccessUnassignedSubmission = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles));
            if ($canAccessUnassignedSubmission) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            } else {
                $this->setAdvice(self::AUTHORIZATION_ADVICE_DENY_MESSAGE, 'editor.submission.workflowDecision.noUnassignedDecisions');
                return AuthorizationPolicy::AUTHORIZATION_DENY;
            }
        } else {
            $isAllowed = false;
            foreach ($stageAssignments as $stageAssignment) {
                $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId);
                if (!in_array($userGroup->getRoleId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])) {
                    continue;
                }
                if (Repo::decision()->isRecommendation($decisionType->getDecision()) && $stageAssignment->recommendOnly) {
                    $isAllowed = true;
                } elseif (!$stageAssignment->recommendOnly) {
                    $isAllowed = true;
                }

                // Check whether there is a decision that a recommending role can make on the stage the submission is in.
                $recommendatorsAvailableDecisions = Repo::decision()
                    ->getDecisionTypesMadeByRecommendingUsers($submission->getData('stageId'));

                // if there is any decision that the recommending role is allowed to make, check if the current decision is within the allowed ones
                if (!empty($recommendatorsAvailableDecisions)) {
                    $matches = array_filter($recommendatorsAvailableDecisions, function ($decisionInArray) use ($decisionType) {
                        return $decisionInArray->getDecision() === $decisionType->getDecision();
                    });

                    if (!empty($matches)) {
                        $isAllowed = true;
                    }
                }
            }
            if ($isAllowed) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\DecisionAllowedPolicy', '\DecisionAllowedPolicy');
}
