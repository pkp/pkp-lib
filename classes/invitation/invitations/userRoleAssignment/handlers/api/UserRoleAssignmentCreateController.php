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

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\validation\ValidatorFactory;

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
        $reqInput = $illuminateRequest->all();
        $payload = $reqInput['invitationData'];

        $context = $this->request->getContext();
        $inviter = $this->request->getUser();

        $this->invitation->initialize($payload['userId'], $context->getId(), $payload['email'], $inviter->getId());

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

        $rules = $this->invitation->getValidationRules();

        // Perform validation
        $validator = ValidatorFactory::make(
            $payload,
            $rules,
            []
        );

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->invitation->fillFromArgs($payload);

        $this->invitation->updatePayload();

        // Here we should consider returning a certain json taken from the custom invitation
        // in order to be able to fully control the response
        return response()->json($this->invitation, Response::HTTP_OK);
    }
    
    /**
     * @inheritDoc
     */
    public function get(Request $illuminateRequest): JsonResponse 
    {
        return response()->json(
            $this->invitation, Response::HTTP_OK
        );
    }
    
    /**
     * @inheritDoc
     */
    public function invite(Request $illuminateRequest): JsonResponse 
    {
        $data = [
            'userGroupsToAdd' => $this->invitation->userGroupsToAdd,
            'userGroupsToRemove' => $this->invitation->userGroupsToRemove,
        ];

        $rules = $this->invitation->getValidationRules();
        $inviteRules = $this->invitation->getInviteValidationRules();

        $rules = array_merge($rules, $inviteRules);

        $validator = ValidatorFactory::make($data, $rules);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->invitation->invite();

        return response()->json(
            $this->invitation, 
            Response::HTTP_OK
        );
    }
}
