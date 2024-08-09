<?php

/**
 * @file classes/invitation/invitations/userRoleAssignment/handlers/api/UserRoleAssignmentReceiveController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserRoleAssignmentReceiveController
 *
 * @brief The controller that defines the "Receive" stage functions of the invitation
 */

namespace PKP\invitation\invitations\userRoleAssignment\handlers\api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKPRequest;

class UserRoleAssignmentReceiveController extends ReceiveInvitationController
{
    public function __construct(public UserRoleAssignmentInvite $invitation) 
    {
    }

    /**
     * @inheritDoc
     */
    public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool 
    {
        return true;
    }
    
    /**
     * @inheritDoc
     */
    public function decline(Request $illuminateRequest): JsonResponse 
    {
        $this->invitation->decline();
        $this->invitation->fillCustomProperties();

        return response()->json(
            $this->invitation,
            Response::HTTP_OK
        );
    }
    
    /**
     * @inheritDoc
     */
    public function finalize(Request $illuminateRequest): JsonResponse 
    {
        $this->invitation->finalize();
        $this->invitation->fillCustomProperties();

        return response()->json(
            $this->invitation,
            Response::HTTP_OK
        );
    }
    
    /**
     * @inheritDoc
     */
    public function receive(Request $illuminateRequest): JsonResponse 
    {
        $this->invitation->fillCustomProperties();

        return response()->json(
            $this->invitation, 
            Response::HTTP_OK
        );
    }
    
    /**
     * @inheritDoc
     */
    public function refine(Request $illuminateRequest): JsonResponse 
    {
        $reqInput = $illuminateRequest->all();
        $payload = $reqInput['invitationData'];

        $this->invitation->fillFromArgs($payload);

        $this->invitation->updatePayload();

        if (!$this->invitation->isValid()) {
            // This can be generalised inside the HasValidation trait
            $response = [
                'invitation' => $this->invitation,
                'validationError' => !$this->invitation->isValid(),
                'errors' => $this->invitation->getErrors(),
            ];

            return response()->json(
                $response, 
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        }

        return response()->json(
            $this->invitation, 
            Response::HTTP_OK
        );
    }
}
