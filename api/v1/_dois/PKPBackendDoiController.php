<?php

/**
 * @file api/v1/_dois/PKPBackendDoiController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendDoiController
 *
 * @ingroup api_v1_backend
 *
 * @brief Controller class to handle API requests for backend operations.
 *
 */

namespace PKP\API\v1\_dois;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\API\v1\reviews\resources\ReviewRoundAuthorResponseResource;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\DoisEnabledPolicy;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\GenreDAO;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;

class PKPBackendDoiController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_dois';
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
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::put('publications/{publicationId}', $this->editPublication(...))
            ->name('_doi.backend.publication.edit')
            ->whereNumber('publicationId');
        Route::put('peerReviews/{reviewId}', $this->editPeerReview(...))
            ->name('_doi.backend.peerReview.edit')
            ->whereNumber('reviewId');
        Route::put('authorResponses/{responseId}', $this->editAuthorResponse(...))
            ->name('_doi.backend.authorResponse.edit')
            ->whereNumber('responseId');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        // This endpoint is not available at the site-wide level
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

        $this->addPolicy(new DoisEnabledPolicy($request->getContext()));

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }

        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }


    /**
     * Edit publication to add DOI
     *
     * @throws \Exception
     */
    public function editPublication(Request $illuminateRequest): JsonResponse
    {
        $context = $this->getRequest()->getContext();

        $publication = Repo::publication()->get($illuminateRequest->route('publicationId'));
        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $submission = Repo::submission()->get($publication->getData('submissionId'));
        if ($submission->getData('contextId') !== $context->getId()) {
            return response()->json([
                'error' => __('api.dois.403.editItemOutOfContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(\PKP\services\PKPSchemaService::SCHEMA_PUBLICATION, $illuminateRequest->input());

        $doi = Repo::doi()->get((int) $params['doiId']);
        if (!$doi) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        Repo::publication()->edit($publication, ['doiId' => $doi->getId()]);
        $publication = Repo::publication()->get($publication->getId());

        $submission = Repo::submission()->get($publication->getData('submissionId'));

        $genreDao = DAORegistry::getDAO('GenreDAO'); /** @var GenreDAO $genreDao */
        $genres = $genreDao->getByContextId($submission->getData('contextId'))->toAssociativeArray();

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $genres)->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * Edit ReviewAssignment (object containing peer review data) to add DOI
     */
    public function editPeerReview(Request $illuminateRequest): JsonResponse
    {
        $reviewAssignment = Repo::reviewAssignment()->get($illuminateRequest->route('reviewId'));
        if (!$reviewAssignment) {
            return response()->json([
                'error' => __('api.404.resourceNotFound')
            ], Response::HTTP_NOT_FOUND);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_REVIEW_ASSIGNMENT, $illuminateRequest->input());
        $doi = Repo::doi()->get((int) $params['doiId']);
        if (!$doi) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound'),
            ], Response::HTTP_NOT_FOUND);

        }

        Repo::reviewAssignment()->edit($reviewAssignment, ['doiId' => $doi->getId()]);
        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignment->getId());

        $submission = Repo::submission()->get($reviewAssignment->getData('submissionId'));

        return response()->json(
            Repo::reviewAssignment()->getSchemaMap()->map($reviewAssignment, $submission),
            Response::HTTP_OK
        );
    }

    /**
     * Edit AuthorResponse to add DOI
     */
    public function editAuthorResponse(Request $illuminateRequest): JsonResponse
    {
        $authorResponse = AuthorResponse::find($illuminateRequest->route('responseId'));
        if (!$authorResponse) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $doi = Repo::doi()->get((int) $illuminateRequest->input('doiId'));
        if (!$doi) {
            return response()->json([
                'error' => __('api.dois.404.doiNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $authorResponse->doiId = $doi->getId();
        $authorResponse->save();

        return response()->json(
            new ReviewRoundAuthorResponseResource($authorResponse),
            Response::HTTP_OK
        );
    }
}
