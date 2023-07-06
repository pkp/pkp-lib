<?php
/**
 * @file classes/security/authorization/internal/SubmissionAuthorPolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionAuthorPolicy
 *
 * @ingroup security_authorization_internal
 *
 * @brief Class to control access to a submission based on authorship.
 *
 * NB: This policy expects a previously authorized submission in the
 * authorization context.
 */

namespace PKP\security\authorization\internal;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\Role;
use PKP\user\User;

class SubmissionAuthorPolicy extends AuthorizationPolicy
{
    /** @var PKPRequest */
    public $_request;

    /**
     * Constructor
     *
     * @param PKPRequest $request
     */
    public function __construct($request)
    {
        parent::__construct('user.authorization.submissionAuthor');
        $this->_request = $request;
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
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission instanceof Submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $context = $this->_request->getContext();

        // Check authorship of the submission. Any ROLE_ID_AUTHOR assignment will do.
        $accessibleWorkflowStages = Repo::user()->getAccessibleWorkflowStages(
            $user->getId(),
            $context->getId(),
            $submission,
            $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_USER_ROLES)
        );

        if (empty($accessibleWorkflowStages)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        foreach ($accessibleWorkflowStages as $roles) {
            if (in_array(Role::ROLE_ID_AUTHOR, $roles)) {
                $this->addAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES, $accessibleWorkflowStages);
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\security\authorization\internal\SubmissionAuthorPolicy', '\SubmissionAuthorPolicy');
}
