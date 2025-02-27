<?php

/**
 * @file api/v1/reviewers/recommendations/ReviewerRecommendationController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendationController
 *
 * @brief API controller class to handle actions on reviewer recommendations
 *
 */

namespace PKP\API\v1\reviewers\recommendations;

use APP\core\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\reviewers\recommendations\formRequests\UpdateStatusReviewerRecommendation;
use PKP\API\v1\reviewers\recommendations\resources\ReviewerRecommendationResource;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\API\v1\reviewers\recommendations\formRequests\AddReviewerRecommendation;
use PKP\API\v1\reviewers\recommendations\formRequests\EditReviewerRecommendation;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class ReviewerRecommendationController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'reviewers/recommendations';
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
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('{recommendationId}', $this->get(...))
            ->name('reviewer.recommendations.get')
            ->whereNumber(['recommendationId']);

        Route::get('', $this->getMany(...))
            ->name('reviewer.recommendations.getMany');

        Route::post('', $this->add(...))
            ->name('reviewer.recommendations.add');

        Route::put('{recommendationId}', $this->edit(...))
            ->name('reviewer.recommendations.edit')
            ->whereNumber(['recommendationId']);
        
        Route::put('{recommendationId}/status', $this->updateStatus(...))
            ->name('reviewer.recommendations.edit.status')
            ->whereNumber(['recommendationId']);

        Route::delete('{recommendationId}', $this->delete(...))
            ->name('reviewer.recommendations.delete')
            ->whereNumber(['recommendationId']);
    }

    /**
     * Get specific recommendation response
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $recommendation = ReviewerRecommendation::find($illuminateRequest->route('recommendationId'));

        if (!$recommendation) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json(
            (new ReviewerRecommendationResource($recommendation))->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    /**
     * Get all recommendations response
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $recommendations = ReviewerRecommendation::query()
            ->withContextId(Application::get()->getRequest()->getContext()->getId())
            ->get();

        return response()->json([
            'items' => ReviewerRecommendationResource::collection($recommendations),
            'itemMax' => $recommendations->count(),
        ], Response::HTTP_OK);
    }

    /**
     * Add new recommendation
     */
    public function add(AddReviewerRecommendation $illuminateRequest): JsonResponse
    {
        $validateds = $illuminateRequest->validated();

        $recommendation = ReviewerRecommendation::create($validateds);

        return response()->json(
            (new ReviewerRecommendationResource($recommendation->refresh()))
                ->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    /**
     * Update existing recommendation
     */
    public function edit(EditReviewerRecommendation $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();

        $recommendation = ReviewerRecommendation::find($illuminateRequest->route('recommendationId'));

        if (!$recommendation->removable) {
            return response()->json([
                'error' => __('api.406.notAcceptable'),
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        if (!$recommendation->update($validated)) {
            return response()->json([
                'error' => __('api.409.resourceActionConflict'),
            ], Response::HTTP_CONFLICT);
        }

        return response()->json(
            (new ReviewerRecommendationResource($recommendation->refresh()))
                ->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    /**
     * Update the status of existing recommendation
     */
    public function updateStatus(UpdateStatusReviewerRecommendation $illuminateRequest): JsonResponse
    {
        $validated = $illuminateRequest->validated();

        $recommendation = ReviewerRecommendation::find($illuminateRequest->route('recommendationId'));

        $recommendation->update($validated);

        return response()->json(
            (new ReviewerRecommendationResource($recommendation->refresh()))
                ->toArray($illuminateRequest), 
            Response::HTTP_OK
        );
    }

    /**
     * Delete existing recommendation
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $recommendation = ReviewerRecommendation::find($illuminateRequest->route('recommendationId'));

        if (!$recommendation) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$recommendation->removable) {
            return response()->json([
                'error' => __('api.406.notAcceptable'),
            ], Response::HTTP_NOT_ACCEPTABLE);
        }

        $recommendation->delete();

        return response()->json([], Response::HTTP_OK);
    }
}
