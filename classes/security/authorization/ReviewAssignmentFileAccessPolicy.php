<?php

/**
 * @file classes/security/authorization/ReviewAssignmentFileAccessPolicy.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentFileAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control read access to review files based on whether the user is an assigned reviewer.
 *
 */

namespace PKP\security\authorization;

use APP\core\Application;
use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

class ReviewAssignmentFileAccessPolicy extends AuthorizationPolicy
{
    public function __construct(
        private PKPRequest $request,
        private int $reviewAssignmentId
    ) {
        parent::__construct('user.authorization.unauthorizedReviewAssignment');
    }

    public function effect(): int
    {
        $user = $this->request->getUser();
        if (!$user instanceof User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);
        if (!($reviewAssignment instanceof \PKP\submission\reviewAssignment\ReviewAssignment)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Managers and site admins can access review files
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            $this->addAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Editors with a stage assignment have access to files
        $stageAssignments = StageAssignment::with('userGroup')
            ->withSubmissionIds([$reviewAssignment->getSubmissionId()])
            ->withStageIds([$reviewAssignment->getStageId()])
            ->get();

        foreach ($stageAssignments as $stageAssignment) {
            if ($stageAssignment->userGroup->roleId != Role::ROLE_ID_SUB_EDITOR) {
                continue;
            }

            if ($stageAssignment->userId === $user->getId()) {
                $this->addAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        // Deny the access for reviewers if user isn't assigned
        if ($reviewAssignment->getReviewerId() !== $user->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Access to files of the cancelled and declined assignments is not allowed
        if ($reviewAssignment->getCancelled() || $reviewAssignment->getDeclined()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $this->addAuthorizedContextObject(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}
