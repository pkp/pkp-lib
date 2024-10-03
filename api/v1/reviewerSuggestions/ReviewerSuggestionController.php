<?php

/**
 * @file api/v1/reviewerSuggestions/ReviewerSuggestionController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestionController
 *
 * @brief 
 *
 */

namespace PKP\API\v1\reviewerSuggestions;

use APP\core\Application;
use PKP\API\v1\reviewerSuggestions\formRequests\AddReviewerSuggestion;

use PKP\security\authorization\internal\SubmissionIncompletePolicy;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPApplication;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\log\EmailLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class ReviewerSuggestionController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'reviewerSuggestions';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     *
     * @throws \Exception
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            'has.context',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_AUTHOR,
            ]),
        ];
    }

    /**
     * @throws \Exception
     */
    public function getGroupRoutes(): void
    {
        Route::get('{suggestionId}', $this->get(...))
            ->name('reviewer.suggestions.get')
            ->whereNumber('suggestionId');

        Route::get('submission/{submissionId}', $this->getMany(...))
            ->name('reviewer.suggestions.getMany')
            ->whereNumber('submissionId');

        Route::post('submission/{submissionId}', $this->add(...))
            ->name('reviewer.suggestions.add');
        
        Route::put('{suggestionId}', $this->edit(...))
            ->name('reviewer.suggestions.edit')
            ->whereNumber('suggestionId');
        
        Route::delete('{suggestionId}', $this->delete(...))
            ->name('reviewer.suggestions.delete')
            ->whereNumber('suggestionId');
        
        Route::post('{suggestionId}', $this->approve(...))
            ->name('reviewer.suggestions.approve')
            ->whereNumber('suggestionId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $illuminateRequest = $args[0]; /** @var \Illuminate\Http\Request $illuminateRequest */
        $actionName = static::getRouteActionName($illuminateRequest);

        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        if (in_array($actionName, ['add', 'edit', 'delete'])) {
            $this->addPolicy(new SubmissionIncompletePolicy($request, $args));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        return response()->json([], Response::HTTP_OK);
    }

    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        return response()->json([], Response::HTTP_OK);
    }

    public function add(AddReviewerSuggestion $illuminateRequest): JsonResponse
    {
        $validateds = $illuminateRequest->validated();
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        return response()->json([], Response::HTTP_OK);
    }

    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        return response()->json([], Response::HTTP_OK);
    }

    public function delete(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        return response()->json([], Response::HTTP_OK);
    }

    public function approve(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();

        return response()->json([], Response::HTTP_OK);
    }
}
