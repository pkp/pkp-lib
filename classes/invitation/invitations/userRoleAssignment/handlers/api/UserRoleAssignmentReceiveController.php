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

use APP\facades\Repo;
use Core;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\PKPBaseController;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\Invitation;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\invitations\userRoleAssignment\helpers\UserGroupHelper;
use PKP\invitation\invitations\userRoleAssignment\resources\UserRoleAssignmentInviteResource;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\security\authorization\AnonymousUserPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKPRequest;
use Validation;

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
        $user = $this->invitation->getExistingUser();
        if (!isset($user)) {
            $controller->addPolicy(new AnonymousUserPolicy($request));
        } else {
            // Register the user object in the session
            $reason = null;
            Validation::registerUserSession($user, $reason);

            $controller->addPolicy(new UserRequiredPolicy($request));
        }
        
        return true;
    }

    /**
     * @inheritDoc
     */
    public function decline(Request $illuminateRequest): JsonResponse 
    {
        $this->invitation->decline();

        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

    /**
     * @inheritDoc
     */
    public function finalize(Request $illuminateRequest): JsonResponse 
    {
        if (!$this->invitation->validate([], ValidationContext::VALIDATION_CONTEXT_FINALIZE)) {
            return response()->json([
                'errors' => $this->invitation->getErrors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->invitation->getExistingUser();

        if (!isset($user)) {
            $user = Repo::user()->newDataObject();

            $user->setUsername($this->invitation->getPayload()->username);

            // Set the base user fields (name, etc.)
            $user->setGivenName($this->invitation->getPayload()->givenName, null);
            $user->setFamilyName($this->invitation->getPayload()->familyName, null);
            $user->setEmail($this->invitation->getEmail());
            $user->setCountry($this->invitation->getPayload()->userCountry);
            $user->setAffiliation($this->invitation->getPayload()->affiliation, null);

            $user->setOrcid($this->invitation->getPayload()->userOrcid);

            $user->setDateRegistered(Core::getCurrentDate());
            $user->setInlineHelp(1); // default new users to having inline help visible.
            $user->setPassword($this->invitation->getPayload()->password);

            Repo::user()->add($user);
        } else {
            if (empty($user->getOrcid()) && isset($this->invitation->getPayload()->userOrcid)) {
                $user->setOrcid($this->invitation->getPayload()->userOrcid);
                Repo::user()->edit($user);
            }
        }

        foreach ($this->invitation->getPayload()->userGroupsToRemove as $userUserGroup) {
            $userGroupHelper = UserGroupHelper::fromArray($userUserGroup);
            Repo::userGroup()->endAssignments(
                $this->invitation->getContextId(),
                $user->getId(),
                $userGroupHelper->userGroupId
            );
        }

        foreach ($this->invitation->getPayload()->userGroupsToAdd as $userUserGroup) {
            $userGroupHelper = UserGroupHelper::fromArray($userUserGroup);

            Repo::userGroup()->assignUserToGroup(
                $user->getId(),
                $userGroupHelper->userGroupId,
                $userGroupHelper->dateStart,
                $userGroupHelper->dateEnd,
                isset($userGroupHelper->masthead) && $userGroupHelper->masthead 
                    ? UserUserGroupMastheadStatus::STATUS_ON 
                    : UserUserGroupMastheadStatus::STATUS_OFF
            );
        }

        $this->invitation->invitationModel->markAs(InvitationStatus::ACCEPTED);

        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

    /**
     * @inheritDoc
     */
    public function receive(Request $illuminateRequest): JsonResponse 
    {
        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest), 
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

        if (!$this->invitation->validate($payload, ValidationContext::VALIDATION_CONTEXT_REFINE)) {
            return response()->json([
                'errors' => $this->invitation->getErrors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->invitation->fillFromData($payload);

        $this->invitation->updatePayload(ValidationContext::VALIDATION_CONTEXT_REFINE);

        return response()->json(
            (new UserRoleAssignmentInviteResource($this->invitation))->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }
}
