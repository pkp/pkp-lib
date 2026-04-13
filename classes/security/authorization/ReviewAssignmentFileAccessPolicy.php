<?php

/**
 * @file classes/security/authorization/ReviewAssignmentFileAccessPolicy.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentFileAccessPolicy
 *
 * @ingroup security_authorization
 *
 * @brief Policy to authorize reviewer file access based on review assignment.
 */

namespace PKP\security\authorization;

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\user\User;

class ReviewAssignmentFileAccessPolicy extends AuthorizationPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param int $reviewAssignmentId The review assignment ID to validate
     */
    public function __construct(
        private PKPRequest $request,
        private int $reviewAssignmentId
    ) {
        parent::__construct('user.authorization.unauthorizedReviewAssignment');
    }

    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect(): int
    {
        $user = $this->request->getUser();
        if (!$user instanceof User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);
        if (!$reviewAssignment instanceof ReviewAssignment) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Deny the access if user isn't assigned
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
