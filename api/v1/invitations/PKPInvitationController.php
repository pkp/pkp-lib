<?php

/**
 * @file api/v1/institutions/PKPInvitationController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPInvitationController
 *
 * @ingroup api_v1_invitations
 *
 * @brief Controller class to handle API requests for invitations operations.
 *
 */

namespace PKP\API\v1\invitations;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;

class PKPInvitationController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'invitations';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('{invitationId}/key/{key}', $this->get(...))
            ->name('invitation.getInvitation')
            ->whereNumber('invitationId');

        Route::put('updatePayload/{invitationId}/key/{key}', $this->updatePayload(...))
            ->name('invitation.edit')
            ->whereNumber('invitationId');
        
        Route::put('accept/{invitationId}/key/{key}', $this->finaliseAccept(...))
            ->name('invitation.acceptInvitation')
            ->whereNumber('invitationId');
        
        Route::put('decline/{invitationId}/key/{key}', $this->finaliseDecline(...))
            ->name('invitation.declineInvitation')
            ->whereNumber('invitationId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        return true;
    }

    /**
     * Get a single invitation
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $invitationKey = $illuminateRequest->route('key');
        $invitationId = (int) $illuminateRequest->route('invitationId');

        $invitation = Repo::invitation()
            ->getByIdAndKey($invitationId, $invitationKey);
    
        if (is_null($invitation)) {
            return response()->json([
                'error' => __('api.invitations.404.invitationNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json($invitation, Response::HTTP_OK);
    }

    /**
     * Update an invitation payload
     */
    public function updatePayload(Request $illuminateRequest): JsonResponse
    {
        $invitationKey = $illuminateRequest->route('key');
        $invitationId = (int) $illuminateRequest->route('invitationId');

        $invitation = Repo::invitation()
            ->getByIdAndKey($invitationId, $invitationKey);
    
        if (is_null($invitation)) {
            return response()->json([
                'error' => __('api.invitations.404.invitationNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Attempt to update the invitation payload in the database
        try {
            $updatedInvitation = Repo::invitation()->updatePayload($invitation, $illuminateRequest->input());

            return response()->json([
                'message' => __('api.invitations.200.invitationUpdatedSuccessfully'),
                'invitation' => $updatedInvitation
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('api.invitations.500.invitationFailedToUpdate')
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Accept an invitation
     */
    public function finaliseAccept(Request $illuminateRequest): JsonResponse
    {
        $invitationKey = $illuminateRequest->route('key');
        $invitationId = (int) $illuminateRequest->route('invitationId');

        $invitation = Repo::invitation()
            ->getByIdAndKey($invitationId, $invitationKey);
    
        if (is_null($invitation)) {
            return response()->json([
                'error' => __('api.invitations.404.invitationNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Attempt to acceptHandle
        try {
            $invitation->finaliseAccept();

            return response()->json([
                'message' => __('api.invitations.200.invitationAccepted'),
                'invitation' => $invitation
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('api.invitations.500.invitationAcceptedFailed'),
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Decline an invitation
     */
    public function finaliseDecline(Request $illuminateRequest): JsonResponse
    {
        $invitationKey = $illuminateRequest->route('key');
        $invitationId = (int) $illuminateRequest->route('invitationId');

        $invitation = Repo::invitation()
            ->getByIdAndKey($invitationId, $invitationKey);
    
        if (is_null($invitation)) {
            return response()->json([
                'error' => __('api.invitations.404.invitationNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        // Attempt to acceptHandle
        try {
            $invitation->finaliseDecline();

            return response()->json([
                'message' => __('api.invitations.200.invitationDeclined'),
                'invitation' => $invitation
            ], Response::HTTP_OK);
        } catch (\Exception $e) {
            return response()->json([
                'error' => __('api.invitations.500.invitationDeclinedFailed'),
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
