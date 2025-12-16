<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers\api;

use APP\facades\Repo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\invitation\core\enums\InvitationStatus;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\invitations\reviewerAccess\resources\ReviewerAccessInviteResource;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\invitations\userRoleAssignment\helpers\UserGroupHelper;
use PKP\security\authorization\AnonymousUserPolicy;
use PKP\security\authorization\AuthorizationPolicy;
use PKP\security\authorization\UserRequiredPolicy;
use PKP\security\Validation;
use PKP\submission\reviewRound\ReviewRoundDAO;

class ReviewerAccessInviteReceiveController extends ReceiveInvitationController
{
    public function __construct(public ReviewerAccessInvite $invitation)
    {
    }

    public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $loggedInUser = $request->getUser();

        $user = $this->invitation->getExistingUser();
        if (!isset($user)) {
            $controller->addPolicy(new AnonymousUserPolicy($request));
        } else {
            // if there is no one logged-in, the user that the invitation is for, can login automatically
            if (!isset($loggedInUser)) {
                // Register the user object in the session
                $reason = null;
                Validation::registerUserSession($user, $reason);
            }

            // if there is a logged-in user and the user is not the invitation's user, then the user should not be allowed
            // to perform the action
            if (isset($loggedInUser) && ($loggedInUser->getId() != $user->getId())) {
                $controller->addPolicy(new AuthorizationPolicy());
            }

            $controller->addPolicy(new UserRequiredPolicy($request));
        }

        return true;
    }

    public function receive(Request $illuminateRequest): JsonResponse
    {
        return response()->json(
            (new ReviewerAccessInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

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
            (new ReviewerAccessInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

    public function finalize(Request $illuminateRequest): JsonResponse
    {
        if (!$this->invitation->validate([], ValidationContext::VALIDATION_CONTEXT_FINALIZE)) {
            return response()->json([
                'errors' => $this->invitation->getErrors()
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user = $this->invitation->getExistingUser() ? $this->invitation->getExistingUser() :$this->invitation->getExistingUserByEmail();

        if (!isset($user)) {
            $user = Repo::user()->newDataObject();

            $user->setUsername($this->invitation->getPayload()->username);

            // Set the base user fields (name, etc.)
            $user->setGivenName($this->invitation->getPayload()->givenName, null);
            $user->setFamilyName($this->invitation->getPayload()->familyName, null);
            $user->setEmail($this->invitation->getEmail());
            $user->setCountry($this->invitation->getPayload()->userCountry);
            $user->setAffiliation($this->invitation->getPayload()->affiliation, null);

            $user->setVerifiedOrcidOAuthData($this->invitation->getPayload()->toArray());

            $user->setDateRegistered(Core::getCurrentDate());
            $user->setInlineHelp(1); // default new users to having inline help visible.
            $user->setPassword($this->invitation->getPayload()->password);

            Repo::user()->add($user);
            // Insert the user interests
            if($this->invitation->getPayload()->userInterests[Locale::getPrimaryLocale()]){
                Repo::userInterest()->setInterestsForUser(
                    $user,
                    array_column(
                        $this->invitation->getPayload()->userInterests[Locale::getPrimaryLocale()], 'name'
                    )
                );
            }
        } else {
            if (empty($user->getOrcid()) && isset($this->invitation->getPayload()->orcid)) {
                $user->setVerifiedOrcidOAuthData($this->invitation->getPayload()->toArray());
                Repo::user()->edit($user);
            }
        }

        foreach ($this->invitation->getPayload()->userGroupsToAdd as $userUserGroup) {
            $userGroupHelper = UserGroupHelper::fromArray($userUserGroup);

            $dateStart = Carbon::parse($userGroupHelper->dateStart)->startOfDay();
            $today = Carbon::today();

            // Use today's date if dateStart is in the past, otherwise keep dateStart
            $effectiveDateStart = $dateStart->lessThan($today) ? $today->toDateString() : $dateStart->toDateString();

            $userHasGroup = Repo::userGroup()->contextHasGroup(
                $this->invitation->getContextId(),
                $userGroupHelper->userGroupId
            );
            if(!$userHasGroup){
                Repo::userGroup()->assignUserToGroup(
                    $user->getId(),
                    $userGroupHelper->userGroupId,
                    $effectiveDateStart,
                    $userGroupHelper->dateEnd,
                    (isset($userGroupHelper->masthead) && $userGroupHelper->masthead)
                );
            }
        }
        // find existing invitation and update invitation
        $existingInvitation = Repo::invitation()->findExistingInvitation($this->invitation);
        // update existing after create user account invitations
        // then the invitation work as existing  reviewer invitation
        foreach ($existingInvitation as $invitation) {
                $updatedData = Repo::invitation()->getById(
                    $invitation->id,
                );
            $updatedData->invitationModel->userId = $user->getId();
            $updatedData->invitationModel->email = null;
            $updatedData->invitationModel->save();
        }
        // update review assignment
        $reviewAssignment = Repo::reviewAssignment()->get(
            $this->invitation->getPayload()->reviewAssignmentId,
            $this->invitation->getPayload()->submissionId,
        );
        Repo::reviewAssignment()->edit($reviewAssignment, [
            'reviewerId' => $user->getId(), // Set the reviewer id
            'dateConfirmed' => Core::getCurrentDate(), // Set the date confirmed
        ]);

        $this->invitation->invitationModel->markAs(InvitationStatus::ACCEPTED);
        return response()->json(
            new ReviewerAccessInviteResource($this->invitation)->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }

    public function decline(Request $illuminateRequest): JsonResponse
    {
        $this->invitation->decline();

        return response()->json(
            new ReviewerAccessInviteResource($this->invitation)->toArray($illuminateRequest),
            Response::HTTP_OK
        );
    }
}
