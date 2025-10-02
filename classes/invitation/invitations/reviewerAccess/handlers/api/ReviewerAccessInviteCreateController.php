<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers\api;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\invitation\invitations\userRoleAssignment\resources\UserRoleAssignmentInviteResource;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;

class ReviewerAccessInviteCreateController extends CreateInvitationController
{
    public function __construct(public ReviewerAccessInvite $invitation)
    {
    }

    public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->request = $request;

        $controller->addPolicy(new UserRolesRequiredPolicy($request), true);

        $controller->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return true;
    }

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

    public function invite(Request $illuminateRequest): JsonResponse
    {
        $this->invitation->getPayload()->sendEmailAddress = $this->invitation->getEmail();

        $existingUser = $this->invitation->getExistingUser();
        if (isset($existingUser)) {
            $this->invitation->getPayload()->sendEmailAddress = $existingUser->getEmail();
        }

        $this->invitation->getPayload()->inviteStagePayload = $this->invitation->getPayload()->toArray();

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

    public function get(Request $illuminateRequest): JsonResponse
    {
        // TODO: Implement get() method.
    }

    public function cancel(): JsonResponse
    {
        // TODO: Implement cancel() method.
    }

    public function getMailable(): JsonResponse
    {
        // TODO: Implement getMailable() method.
    }

    public function getMany(Request $illuminateRequest, Builder $query): Collection
    {
        // TODO: Implement getMany() method.
    }
}
