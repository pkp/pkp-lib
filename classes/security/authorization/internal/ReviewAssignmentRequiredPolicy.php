<?php
/**
 * @file classes/security/authorization/internal/ReviewAssignmentRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewAssignmentRequiredPolicy
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid review assignment.
 */

namespace PKP\security\authorization\internal;

use APP\submission\Submission;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;

use PKP\security\authorization\DataObjectRequiredPolicy;

class ReviewAssignmentRequiredPolicy extends DataObjectRequiredPolicy
{
    /** @var array Allowed review methods */
    public $_reviewMethods = [];

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param string $parameterName the request parameter we
     *  expect the submission id in.
     * @param array|string $operations either a single operation or a list of operations that
     *  this policy is targeting.
     * @param array $reviewMethods limit the policy to specific review methods
     */
    public function __construct($request, &$args, $parameterName = 'reviewAssignmentId', $operations = null, $reviewMethods = null)
    {
        parent::__construct($request, $args, $parameterName, 'user.authorization.invalidReviewAssignment', $operations, $reviewMethods);
        $this->_reviewMethods = $reviewMethods;
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        $reviewId = (int)$this->getDataObjectId();
        if (!$reviewId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getById($reviewId);
        if (!($reviewAssignment instanceof \PKP\submission\reviewAssignment\ReviewAssignment)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // If reviewMethods is defined, check that the assignment uses the defined method(s)
        if ($this->_reviewMethods) {
            if (!in_array($reviewAssignment->getReviewMethod(), $this->_reviewMethods)) {
                return AuthorizationPolicy::AUTHORIZATION_DENY;
            }
        }

        // Ensure that the review assignment actually belongs to the
        // authorized submission.
        $submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
        assert($submission instanceof Submission);
        if ($reviewAssignment->getSubmissionId() != $submission->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Ensure that the review assignment is for this workflow stage
        $stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
        if ($reviewAssignment->getStageId() != $stageId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the review Assignment to the authorization context.
        $this->addAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy', '\ReviewAssignmentRequiredPolicy');
}
