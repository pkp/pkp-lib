<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/handlers/api/UserRoleAssignmentCreateController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentCreateController
 *
 */

namespace PKP\invitation\invitations\userRoleAssignment\handlers\api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\Invitation;
use PKP\invitation\invitations\userRoleAssignment\resources\UserRoleAssignmentInviteResource;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;

class UserRoleAssignmentCreateController extends CreateInvitationController
{
    public function __construct(public UserRoleAssignmentInvite $invitation) 
    {
    }

    /**
     * @inheritDoc
     */
    public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool 
    {
        $this->request = $request;

        $controller->addPolicy(new UserRolesRequiredPolicy($request), true);

        $controller->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return true;
    }

    /**
     * @inheritDoc
     */
    public function add(Request $illuminateRequest): JsonResponse 
    {
        if ($this->invitation->getEmail()) {
            $this->invitation->getPayload()->sendEmailAddress = $this->invitation->getEmail();
            $this->invitation->updatePayload();
        }

        return response()->json([
            'invitationId' => $this->invitation->getId()
        ], Response::HTTP_OK);
    }

    /**
     * @inheritDoc
     */
    public function populate(Request $illuminateRequest): JsonResponse 
    {
        $reqInput = $illuminateRequest->all();
        $payload = $reqInput['invitationData'];

        if (!$this->invitation->validate($payload, ValidationContext::VALIDATION_CONTEXT_POPULATE)) {
            return response()->json([
                'errors' => $this->invitation->getErrors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->invitation->fillFromData($payload);

        $this->invitation->updatePayload();

        // Here we should consider returning a certain json taken from the custom invitation
        // in order to be able to fully control the response
        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }
    
    /**
     * @inheritDoc
     */
    public function get(Request $illuminateRequest): JsonResponse 
    {
        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }
    
    /**
     * @inheritDoc
     */
    public function invite(Request $illuminateRequest): JsonResponse 
    {
        $this->invitation->getPayload()->sendEmailAddress = $this->invitation->getEmail();

        $existingUser = $this->invitation->getExistingUser();
        if (isset($existingUser)) {
            $this->invitation->getPayload()->sendEmailAddress = $existingUser->getEmail();
        }

        $this->invitation->updatePayload();
        
        if (!$this->invitation->validate([], ValidationContext::VALIDATION_CONTEXT_INVITE)) {
            return response()->json([
                'errors' => $this->invitation->getErrors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inviteResult = $this->invitation->invite();

        if (!isset($inviteResult)) {
            return response()->json([
                'errors' => $this->invitation->getErrors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }
}
