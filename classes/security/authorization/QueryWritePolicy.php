<?php

/**
 * @file classes/security/authorization/QueryWritePolicy.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryWritePolicy
 *
 * @ingroup security_authorization
 *
 * @brief Class to control access to queries.
 */

namespace PKP\security\authorization;

use APP\core\Application;
use APP\submission\Submission;
use PKP\core\PKPRequest;
use PKP\security\Role;

class QueryWritePolicy extends AuthorizationPolicy
{
    protected PKPRequest $request;

    public function __construct(PKPRequest $request)
    {
        parent::__construct('user.authorization.submissionQuery');
        $this->request = $request;
    }

    public function effect(): int
    {
        $editTask = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_QUERY);
        if (!$editTask) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        /** @var Submission $submission */
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        if (!$submission) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        $user = $this->request->getUser();

        // Admins and managers can always edit of delete queries.
        if ($user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $submission->getData('contextId'))) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Check roles within the stage assignments and review assignments related to the query.
        $accessibleWorkflowStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $roles = $accessibleWorkflowStages[$editTask->stageId];

        // Deny if user isn't associated with the correspondent submission and stage
        if (empty($roles)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // Submission editors can edit or delete any task within submissions they are assigned to.
        if (in_array(Role::ROLE_ID_SUB_EDITOR, $roles)) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Authors and assistants can edit or delete task created by them.
        if (array_intersect([Role::ROLE_ID_AUTHOR, Role::ROLE_ID_ASSISTANT], $roles)) {
            if ($editTask->createdBy === $user->getId()) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        return parent::effect();
    }
}
