<?php

namespace PKP\invitation\invitations\reviewerAccess\handlers\api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\invitation\core\ReceiveInvitationController;
use PKP\invitation\invitations\reviewerAccess\ReviewerAccessInvite;

class ReviewerAccessInviteReceiveController extends ReceiveInvitationController
{
    public function __construct(public ReviewerAccessInvite $invitation)
    {
    }

    public function authorize(PKPBaseController $controller, PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        // TODO: Implement authorize() method.
    }

    public function receive(Request $illuminateRequest): JsonResponse
    {
        // TODO: Implement receive() method.
    }

    public function refine(Request $illuminateRequest): JsonResponse
    {
        // TODO: Implement refine() method.
    }

    public function finalize(Request $illuminateRequest): JsonResponse
    {
        // TODO: Implement finalize() method.
    }

    public function decline(Request $illuminateRequest): JsonResponse
    {
        // TODO: Implement decline() method.
    }
}
