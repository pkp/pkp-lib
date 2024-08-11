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
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\invitations\userRoleAssignment\payload\UserGroupPayload;
use PKP\invitation\invitations\userRoleAssignment\UserRoleAssignmentInvite;
use PKP\userGroup\relationships\enums\UserUserGroupMastheadStatus;
use PKPRequest;
use Exception;
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
        $this->invitation->validateBeforeFinalise();

        if (!$this->invitation->isValid()) {
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

        $user = $this->invitation->getExistingUser();

        if (!isset($user)) {
            $user = Repo::user()->newDataObject();

            $user->setUsername($this->invitation->username);

            // Set the base user fields (name, etc.)
            $user->setGivenName($this->invitation->givenName, null);
            $user->setFamilyName($this->invitation->familyName, null);
            $user->setEmail($this->invitation->invitationModel->email);
            $user->setCountry($this->invitation->country);
            $user->setAffiliation($this->invitation->affiliation, null);

            $user->setOrcid($this->invitation->orcid);

            $user->setDateRegistered(Core::getCurrentDate());
            $user->setInlineHelp(1); // default new users to having inline help visible.
            $user->setPassword(Validation::encryptCredentials($this->invitation->username, $this->invitation->password));

            Repo::user()->add($user);
        }

        foreach ($this->invitation->userGroupsToRemove as $userUserGroup) {
            $userGroupPayload = UserGroupPayload::fromArray($userUserGroup);
            Repo::userGroup()-> deleteAssignmentsByUserId(
                $user->getId(),
                $userGroupPayload->userGroupId
            );
        }

        foreach ($this->invitation->userGroupsToAdd as $userUserGroup) {
            $userGroupPayload = UserGroupPayload::fromArray($userUserGroup);

            Repo::userGroup()->assignUserToGroup(
                $user->getId(),
                $userGroupPayload->userGroupId,
                $userGroupPayload->dateStart,
                $userGroupPayload->dateEnd,
                isset($userGroupPayload->masthead) && $userGroupPayload->masthead 
                    ? UserUserGroupMastheadStatus::STATUS_ON 
                    : UserUserGroupMastheadStatus::STATUS_OFF
            );
        }

        $this->invitation->invitationModel->markAs(InvitationStatus::ACCEPTED);
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
