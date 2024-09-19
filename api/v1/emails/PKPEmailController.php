<?php

/**
 * @file api/v1/email/PKPEmailController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailController
 *
 * @ingroup api_v1_email
 *
 * @brief Controller class to handle API request to for emails
 */

namespace PKP\API\v1\emails;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
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
            Route::get('', $this->getMany(...))
                ->name('emails.getMany');
        });

        Route::middleware([
            self::roleAuthorizer([Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR]),
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

        if ($illuminateRequest->input('submissionId')) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of emails for user
     */
    protected function getMany(Request $illuminateRequest): JsonResponse
    {

        $allowedQueryParams = ['submissionId', 'userId', 'eventType'];
        $currentUser = $this->getRequest()->getUser();

        foreach ($illuminateRequest->query->keys() as $queryParam) {
            if (!in_array($queryParam, $allowedQueryParams)) {
                return response()->json([
                    'error' => __('api.400.paramNotSupported', ['param' => $queryParam]),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        $submissionId = is_numeric($illuminateRequest->input('submissionId')) ? (int)$illuminateRequest->input('submissionId') : null;
        $eventType = is_numeric($illuminateRequest->input('eventType')) ? (int)$illuminateRequest->input('eventType') : null;
        $queryUserId = is_numeric($illuminateRequest->input('userId')) ? (int)$illuminateRequest->input('userId') : null;

        // Check that userId param match user making request
        if ($queryUserId && $queryUserId !== $this->getRequest()->getUser()->getId()) {
            return response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }


        // If submissionID given, check that submission exists and user has access to it
        if ($submissionId) {
            $submission = Repo::submission()->get($submissionId);

            if (!$submission) {
                return response()->json([
                    'error' => __('api.dois.404.submissionNotFound'),
                ], Response::HTTP_NOT_FOUND);
            }

            // Ensure user has access to the submission they're requesting emails from
            $isAssigned = StageAssignment::withSubmissionIds([$submission->getId()])
                ->withRoleIds([Role::ROLE_ID_AUTHOR]) //remove author
                ->withUserId($currentUser->getId())
                ->pluck('user_id')->toArray();

            if (!$isAssigned) {
                return response()->json([
                    'error' => __('api.403.unauthorized'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        if ($eventType && !SubmissionEmailLogEventType::tryFrom($eventType)) {
            return response()->json([
                'error' => __('api.emailLogs.400.unrecognisedEventType', ['eventType' => $eventType]),
            ], Response::HTTP_BAD_REQUEST);
        }


        $emails = EmailLogEntry::leftJoin('email_log_users as u', 'email_log.log_id', '=', 'u.email_log_id')
            ->where('u.user_id', $queryUserId ?? $currentUser->getId()) // use provided userId or default to user making the request
            ->when($eventType, fn ($query) => $query->where('event_type', $eventType))
            ->when($submissionId, fn ($query) => $query->where('assoc_id', $submissionId))
            ->select('email_log.*')->get();

        $data = Repo::emailLogEntry()->getSchemaMap()->summarizeMany($emails);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get a single email object
     */
    protected function getEmail(Request $illuminateRequest): JsonResponse
    {
        $emailId = (int)$illuminateRequest->route('emailId');
        $email = EmailLogEntry::find($emailId);
        $currentUser = $this->getRequest()->getUser();
        $context = $this->getRequest()->getContext();

        if(!$email) {
            return response()->json(['error' => __('api.404.resourceNotFound')], Response::HTTP_NOT_FOUND);
        }

        $isUserRecipient = Repo::emailLogEntry()->isUserEmailRecipient($emailId, $this->getRequest()->getUser()->getId());

        // If user is not a recipient, then check if user is assigned as an editor to the submission that the email is linked to.
        // Admins and Managers can view an email.
        if(!$isUserRecipient) {
            $submission = Repo::submission()->get($email->assocId);
            $isAdmin = $currentUser->hasRole([Role::ROLE_ID_MANAGER], $context->getId()) || $currentUser->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::SITE_CONTEXT_ID);

            if(!$isAdmin) {
                $isAssignedEditor = StageAssignment::withSubmissionIds([$submission->getId()])
                    ->withRoleIds([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR]) //remove author
                    ->withUserId($currentUser->getId())
                    ->pluck('user_id')->toArray();

                if(!$isAssignedEditor) {
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
