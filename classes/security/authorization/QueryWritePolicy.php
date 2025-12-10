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
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\editorialTask\Participant;
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

        // User who created a task can always edit or delete it.
        if ($editTask->createdBy == $user->getId()) {
            return AuthorizationPolicy::AUTHORIZATION_PERMIT;
        }

        // Check roles within the stage assignments and review assignments related to the query.
        $accessibleWorkflowStages = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_ACCESSIBLE_WORKFLOW_STAGES);
        $roles = $accessibleWorkflowStages[$editTask->stageId];

        // Deny if user isn't associated with the correspondent submission and stage
        if (empty($roles)) {
            return AuthorizationPolicy::AUTHORIZATION_DENY;
        }

        // If this is a task and person is assigned as responsible, allow changing it.
        if ($editTask->type == EditorialTaskType::TASK->value) {
            $editTask->loadMissing('participants');
            $currentUser = $editTask->participants->first(fn (Participant $participant) => $participant->userId == $user->getId());
            if ($currentUser && $currentUser->isResponsible) {
                return AuthorizationPolicy::AUTHORIZATION_PERMIT;
            }
        }

        // Otherwise disallow by default
        return AuthorizationPolicy::AUTHORIZATION_DENY;
    }
}
