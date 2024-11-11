<?php

/**
 * @file api/v1/reviewers/suggestions/ReviewerSuggestionController.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSuggestionController
 *
 * @brief Handle API requests for reviewer suggestion operations.
 *
 */

namespace PKP\API\v1\reviewers\suggestions;

use PKP\API\v1\reviewers\suggestions\formRequests\EditReviewerSuggestion;
use PKP\API\v1\reviewers\suggestions\resources\ReviewerSuggestionResource;
use PKP\API\v1\reviewers\suggestions\formRequests\AddReviewerSuggestion;
use PKP\security\authorization\internal\SubmissionIncompletePolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\submission\reviewer\suggestion\ReviewerSuggestion;

class ReviewerSuggestionController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/reviewers/suggestions';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
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

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('{suggestionId}', $this->get(...))
            ->name('reviewer.suggestions.get')
            ->whereNumber('suggestionId');

        Route::get('', $this->getMany(...))
            ->name('reviewer.suggestions.getMany')
            ->whereNumber('submissionId');

        Route::post('', $this->add(...))
            ->name('reviewer.suggestions.add');
        
        Route::put('{suggestionId}', $this->edit(...))
            ->name('reviewer.suggestions.edit')
            ->whereNumber('suggestionId');
        
        Route::delete('{suggestionId}', $this->delete(...))
            ->name('reviewer.suggestions.delete')
            ->whereNumber('suggestionId');
    }

    public function get(Request $illuminateRequest): JsonResponse
    {
        $reviewerSuggestion = ReviewerSuggestion::find($illuminateRequest->route('suggestionId'));
        
        if (!$reviewerSuggestion) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            (new ReviewerSuggestionResource($reviewerSuggestion))->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $suggestions = ReviewerSuggestion::query()
            ->withSubmissionIds($illuminateRequest->route('submissionId'))
            ->when(
                $illuminateRequest->has('approved'),
                fn ($query) => $query->withApproved(
                    filter_var($illuminateRequest->get('approved'), FILTER_VALIDATE_BOOLEAN)
                )
            )
            ->get();

        return response()->json([
            'items' => ReviewerSuggestionResource::collection($suggestions),
            'itemMax' => $suggestions->count(),
        ], Response::HTTP_OK);
    }

    public function add(AddReviewerSuggestion $illuminateRequest): JsonResponse
    {
        $validateds = $illuminateRequest->validated();

        $suggestion = ReviewerSuggestion::create($validateds);
        
        return response()->json(
            (new ReviewerSuggestionResource($suggestion->refresh()))
                ->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    public function edit(EditReviewerSuggestion $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();

        $reviewerSuggestion = ReviewerSuggestion::find($illuminateRequest->route('suggestionId'));
        
        if (!$reviewerSuggestion->update($validated)) {
            return response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT);
        }
            
        return response()->json(
            (new ReviewerSuggestionResource($reviewerSuggestion->refresh()))
                ->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    public function delete(Request $illuminateRequest): JsonResponse
    {
        $reviewerSuggestion = ReviewerSuggestion::find($illuminateRequest->route('suggestionId'));

        if (!$reviewerSuggestion) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $reviewerSuggestion->delete();

        return response()->json([], Response::HTTP_OK);
    }
}
