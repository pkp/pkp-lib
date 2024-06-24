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

use APP\facades\Repo;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\user\User;

class ReviewAssignmentFileAccessPolicy extends AuthorizationPolicy
{
    public function __construct(
        private PKPRequest $request,
        private int $reviewAssignmentId
    ) {
        parent::__construct('user.authorization.unauthorizedReviewAssignment');
    }

    public function effect()
    {
        $user = $this->request->getUser();
        if (!$user instanceof User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $reviewAssignment = Repo::reviewAssignment()->get($this->reviewAssignmentId);
        if (!($reviewAssignment instanceof \PKP\submission\reviewAssignment\ReviewAssignment)) {
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
