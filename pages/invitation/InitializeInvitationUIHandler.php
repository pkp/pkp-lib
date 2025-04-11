<?php

/**
 * @file pages/invitation/InitializeInvitationUIHandler.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InitializeInvitationUIHandler
 *
 * @ingroup pages_invitation
 *
 * @brief Handles page requests for invitations op
 */

namespace PKP\pages\invitation;

use APP\core\Request;
use APP\facades\Repo;
use APP\handler\Handler;
use PKP\invitation\core\Invitation;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class InitializeInvitationUIHandler extends Handler
{
    public $_isBackendPage = true;
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                ROLE::ROLE_ID_ASSISTANT,
            ],
            [
                'create',
                'edit',
            ]
        );
    }

    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRequiredPolicy($request));

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);
        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Create an invitation for a user to accept new roles
     * @param array $args
     * @param Request $request
     */
    public function create(array $args, Request $request): void
    {
        if (empty($args) || count($args) < 1) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $this->setupTemplate($request);

        $arg = $args[0]; // invitation type

        if (is_numeric($arg)) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        } else {
            // Handle new invitation by type
            $invitationType = $arg;
            $invitation = app(Invitation::class)->createNew($invitationType);
            $invitationHandler = $invitation->getInvitationUIActionRedirectController();
            $invitationHandler->createHandle($request);
        }
    }

    /**
     * Edit an invitation
     * @param array $args
     * @param Request $request
     * @return void
     */
    public function edit(array $args, Request $request): void
    {
        if (empty($args) || count($args) < 1) {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }

        $this->setupTemplate($request);

        $arg = $args[0]; // invitation id

        if (is_numeric($arg)) {
            // Handle existing invitation by ID
            $invitationId = (int) $arg;
            $invitation = Repo::invitation()->getById($invitationId);
            if (!$invitation) {
                throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
            }
            $invitationHandler = $invitation->getInvitationUIActionRedirectController();
            $invitationHandler->editHandle($request);
        } else {
            throw new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException();
        }
    }
}
