<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers\api;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\invitation\core\CreateInvitationController;
use PKP\invitation\core\enums\ValidationContext;
use PKP\invitation\invitations\reviewerAccess\resources\ReviewerAccessInviteManagerDataResource;
use PKP\invitation\invitations\reviewerAccess\resources\ReviewerAccessInviteResource;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\submission\reviewRound\ReviewRoundDAO;

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
            (new ReviewerAccessInviteResource($this->invitation))->toArray($illuminateRequest),
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


        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRound = $reviewRoundDao->getById($this->invitation->getPayload()->reviewRoundId);

        $reviewAssignment = Repo::reviewAssignment()->newDataObject();
        $reviewAssignment->setReviewerId($this->invitation->getUserId() ?: null);
        $reviewAssignment->setReviewMethod($this->invitation->getPayload()->reviewMethod);
        $reviewAssignment->setSubmissionId($this->invitation->getPayload()->submissionId);
        $reviewAssignment->setReviewRoundId($this->invitation->getPayload()->reviewRoundId);
        $reviewAssignment->setDateDue($this->invitation->getPayload()->reviewDueDate);
        $reviewAssignment->setDateResponseDue($this->invitation->getPayload()->responseDueDate);
        $reviewAssignment->setDateAssigned(Core::getCurrentDate());
        $reviewAssignment->setDateNotified(Core::getCurrentDate());
        $reviewAssignment->setStageId($reviewRound->getStageId());

        try {
            DB::transaction(function () use ($reviewAssignment) {
                Repo::reviewAssignment()->add($reviewAssignment);
                $this->invitation->getPayload()->reviewAssignmentId = $reviewAssignment->getId();
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
                return $inviteResult;
            });

            return response()->json(
                (new ReviewerAccessInviteResource($this->invitation))->toArray($illuminateRequest),
                Response::HTTP_OK
            );
        } catch (\Throwable $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function get(Request $illuminateRequest): JsonResponse
    {
        return response()->json(
            (new ReviewerAccessInviteResource($this->invitation))->toArray($illuminateRequest),
            Response::HTTP_OK
        );
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
        $count = $illuminateRequest->query('count', 10); // Default count
        $offset = $illuminateRequest->query('offset', 0); // Default offset
        $submissionId = $illuminateRequest->get('submissionId');
        $reviewRoundId = $illuminateRequest->get('$reviewRoundId');

        if ($submissionId) {
            $query->where('payload->submissionId', $submissionId);
        }
        if ($reviewRoundId) {
            $query->where('payload->reviewRoundId', $reviewRoundId);
        }
        // Apply pagination and retrieve results
        $invitations = $query
            ->skip($offset)
            ->take($count)
            ->get();

        $finalCollection = $invitations->map(function ($invitation) {
            $specificInvitation = Repo::invitation()->getById($invitation->id);
            return $specificInvitation;
        });

        return ReviewerAccessInviteManagerDataResource::collection($finalCollection)->collect();
    }
}
