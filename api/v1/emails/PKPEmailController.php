<?php

/**
 * @file api/v1/emails/PKPEmailController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailController
 *
 * @ingroup api_v1_emails
 *
 * @brief Controller class to handle API request for emails
 */

namespace PKP\API\v1\emails;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\log\EmailLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class PKPEmailController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'emails';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([Role::ROLE_ID_AUTHOR]),
        ])->group(function () {
            Route::get('/authorEmails', $this->getMany(...))
                ->name('emails.getMany');
        });

        Route::middleware([
            self::roleAuthorizer([Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER]),
        ])->group(function () {
            Route::get('{emailId}', $this->getEmail(...))
                ->name('emails.getEmail')
                ->whereNumber('emailId');
        });
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0];
        $actionName = static::getRouteActionName($illuminateRequest);

        if ($actionName === 'getMany' && $illuminateRequest->input('submissionId')) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of emails for an author
     *
     * Pass the following query params for filtering:
     * - submissionId: Filter emails by the submission ID they are linked to.
     * - eventType: Filter emails by their event type.
     */
    protected function getMany(Request $illuminateRequest): JsonResponse
    {
        $authorEmailLogEventTypes = [SubmissionEmailLogEventType::EDITOR_NOTIFY_AUTHOR, SubmissionEmailLogEventType::COPYEDIT_NOTIFY_AUTHOR, SubmissionEmailLogEventType::PROOFREAD_NOTIFY_AUTHOR];
        $currentUser = $this->getRequest()->getUser();

        $submissionId = is_numeric($illuminateRequest->input('submissionId')) ? (int)$illuminateRequest->input('submissionId') : null;
        $eventType = is_numeric($illuminateRequest->input('eventType')) ? (int)$illuminateRequest->input('eventType') : null;

        // If submissionID given, check that submission exists and user has access to it
        if ($submissionId) {
            $submission = Repo::submission()->get($submissionId);

            if (!$submission) {
                return response()->json([
                    'error' => __('api.dois.404.submissionNotFound'),
                ], Response::HTTP_NOT_FOUND);
            }

            // Ensure user has access to the submission they're requesting emails from
            $userAssignment = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withRoleIds([Role::ROLE_ID_AUTHOR])
                ->withUserId($currentUser->getId())
                ->first();

            if (!$userAssignment) {
                return response()->json([
                    'error' => __('api.403.unauthorized'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Specific Submission EmailLog EventTypes represents email log entries for actual emails sent to authors.
        // Check that given eventType is one such event
        if ($eventType && !in_array($eventType, array_map(fn ($type) => $type->value, $authorEmailLogEventTypes))) {
            return response()->json([
                'error' => __('api.emailLogs.400.unrecognisedAuthorEmailEventType', ['eventType' => $eventType]),
            ], Response::HTTP_BAD_REQUEST);
        }

        $emails = EmailLogEntry::withRecipientId($currentUser->getId())
            ->withEventTypes($eventType !== null ? [SubmissionEmailLogEventType::from($eventType)] : $authorEmailLogEventTypes) // If no eventType was given then ensure that only email log entries with types for author emails are returned
            ->when($submissionId, fn ($query) => $query->where('assoc_id', $submissionId))
            ->get();

        $data = Repo::emailLogEntry()->getSchemaMap()->summarizeMany($emails);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get a single email object
     */
    protected function getEmail(Request $illuminateRequest): JsonResponse
    {
        $emailId = (int)$illuminateRequest->route('emailId');
        $currentUser = $this->getRequest()->getUser();
        $context = $this->getRequest()->getContext();
        $email = EmailLogEntry::find($emailId);

        if (!$email) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        $isUserRecipient = Repo::emailLogEntry()->isUserEmailRecipient($emailId, $this->getRequest()->getUser()->getId());

        // If user is not a recipient, then check if user is assigned as an editor to the submission that the email is linked to
        // Or if user has admin or managerial roles.
        if (!$isUserRecipient) {
            $submission = Repo::submission()->get($email->assocId);

            $editorAssignment = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
                ->withUserId($currentUser->getId())
                ->first();

            if (!$editorAssignment) {
                $isAdmin = $currentUser->hasRole([Role::ROLE_ID_MANAGER], $context->getId()) || $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);

                if (!$isAdmin) {
                    return response()->json([
                        'error' => __('api.403.unauthorized'),
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $data = Repo::emailLogEntry()->getSchemaMap()->summarize($email);

        return response()->json($data, Response::HTTP_OK);
    }
}
