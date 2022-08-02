<?php
/**
 * @file classes/security/authorization/internal/ReviewAssignmentAccessPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentAccessPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a submission based on whether the user is an assigned reviewer.
 *
 * NB: This policy expects a previously authorized submission in the
 * authorization context.
 */

namespace PKP\security\authorization\internal;

use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\user\User;

class ReviewAssignmentAccessPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /** @var bool */
    public $_permitDeclined;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param bool $permitDeclined True if declined or cancelled reviews are acceptable.
     */
    public function __construct($request, $permitDeclined = false)
    {
        parent::__construct('user.authorization.submissionReviewer');
        $this->_request = $request;
        $this->_permitDeclined = $permitDeclined;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see AuthorizationPolicy::effect()
     */
    public function effect()
    {
        // Get the user
        $user = $this->_request->getUser();
        if (!$user instanceof User) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Get the submission
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        if (!$submission instanceof Submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Check if a review assignment exists between the submission and the user
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer($submission->getId(), $user->getId());

        // Ensure a valid review assignment was fetched from the database
        if (!($reviewAssignment instanceof \PKP\submission\reviewAssignment\ReviewAssignment)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // If the assignment has been cancelled, deny access.
        if ($reviewAssignment->getCancelled()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Ensure that the assignment isn't declined, unless that's permitted
        if (!$this->_permitDeclined && $reviewAssignment->getDeclined()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the review assignment to the authorization context.
        $this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ReviewAssignmentAccessPolicy', '\ReviewAssignmentAccessPolicy');
}
