<?php
/**
 * @file classes/security/authorization/internal/ReviewRoundRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid review round.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;
use PKP\submission\reviewRound\ReviewRound;

class ReviewRoundRequiredPolicy extends DataObjectRequiredPolicy
{
    /** @var int Review round id. */
    public $_reviewRoundId;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param string $parameterName the request parameter we expect
     *  the submission id in.
     * @param array $operations Optional list of operations for which this check takes effect. If specified, operations outside this set will not be checked against this policy.
     * @param int $reviewRoundId Optionally pass the review round id directly. If passed, the $parameterName will be ignored.
     */
    public function __construct($request, &$args, $parameterName = 'reviewRoundId', $operations = null, $reviewRoundId = null)
    {
        parent::__construct($request, $args, $parameterName, 'user.authorization.invalidReviewRound', $operations);
        if ($reviewRoundId) {
            $this->_reviewRoundId = $reviewRoundId;
        }
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        // Get the review round id.
        if (!$this->_reviewRoundId) {
            $this->_reviewRoundId = $this->getDataObjectId();
        }
        if ($this->_reviewRoundId === false) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Validate the review round id.
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getById($this->_reviewRoundId);
        if (!$reviewRound instanceof ReviewRound) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Ensure that the review round actually belongs to the
        // authorized submission.
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if ($reviewRound->getSubmissionId() != $submission->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Ensure that the review round is for this workflow stage
        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
        if ($reviewRound->getStageId() != $stageId) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the review round to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ROUND, $reviewRound);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\ReviewRoundRequiredPolicy', '\ReviewRoundRequiredPolicy');
}
