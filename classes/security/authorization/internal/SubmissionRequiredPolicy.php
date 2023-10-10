<?php
/**
 * @file classes/security/authorization/internal/SubmissionRequiredPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionRequiredPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Policy that ensures that the request contains a valid submission.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\core\PKPRequest;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\DataObjectRequiredPolicy;

class SubmissionRequiredPolicy extends DataObjectRequiredPolicy
{
    /**
     * Constructor
     *
     * @param PKPRequest $request
     * @param array $args request parameters
     * @param string $submissionParameterName the request parameter we expect
     *  the submission id in.
     * @param null|mixed $operations
     */
    public function __construct($request, &$args, $submissionParameterName = 'submissionId', $operations = null)
    {
        parent::__construct($request, $args, $submissionParameterName, 'user.authorization.invalidSubmission', $operations);

        $callOnDeny = [$request->getDispatcher(), 'handle404', []];
        $this->setAdvice(
            AuthorizationPolicy::AUTHORIZATION_ADVICE_CALL_ON_DENY,
            $callOnDeny
        );
    }

    //
    // Implement template methods from AuthorizationPolicy
    //
    /**
     * @see DataObjectRequiredPolicy::dataObjectEffect()
     */
    public function dataObjectEffect()
    {
        // Get the submission id.
        $submissionId = $this->getDataObjectId();
        if ($submissionId === false) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Validate the submission id.
        $submission = Repo::submission()->get((int) $submissionId);
        if (!$submission instanceof Submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Validate that this submission belongs to the current context.
        $context = $this->_request->getContext();
        if ($context->getId() != $submission->getData('contextId')) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Save the submission to the authorization context.
        $this->addAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION, $submission);
        return AuthorizationPolicy::AUTHORIZATION_PERMIT;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionRequiredPolicy', '\SubmissionRequiredPolicy');
}
