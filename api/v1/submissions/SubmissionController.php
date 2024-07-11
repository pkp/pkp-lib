<?php

/**
 * @file api/v1/submissions/SubmissionController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionController
 *
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

namespace APP\API\v1\submissions;

use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\GenreDAO;

class SubmissionController extends \PKP\API\v1\submissions\PKPSubmissionController
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->requiresSubmissionAccess[] = 'relatePublication';
        $this->requiresProductionStageAccess[] = 'relatePublication';
        $this->productionStageAccessRoles[] = Role::ROLE_ID_AUTHOR;
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::put('{submissionId}/publications/{publicationId}/relate', $this->relatePublication(...))
            ->name('submission.publication.relate')
            ->middleware([
                self::roleAuthorizer([
                    Role::ROLE_ID_MANAGER,
                    Role::ROLE_ID_SITE_ADMIN,
                    Role::ROLE_ID_SUB_EDITOR,
                    Role::ROLE_ID_ASSISTANT,
                    Role::ROLE_ID_AUTHOR,
                ]),
            ])
            ->whereNumber(['submissionId', 'publicationId']);

        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {
            Route::post('{submissionId}/publications', $this->addPublication(...))
                ->name('submission.publication.add')
                ->whereNumber('submissionId');

            Route::post('{submissionId}/publications/{publicationId}/version', $this->versionPublication(...))
                ->name('submission.publication.version.get')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}/publish', $this->publishPublication(...))
                ->name('submission.publication.publish')
                ->whereNumber(['submissionId', 'publicationId']);

            Route::put('{submissionId}/publications/{publicationId}/unpublish', $this->unpublishPublication(...))
                ->name('submission.publication.unpublish')
                ->whereNumber(['submissionId', 'publicationId']);
        });

        parent::getGroupRoutes();
    }

    /**
     * Create relations for publications
     */
    public function relatePublication(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $submission = Repo::submission()->get((int) $illuminateRequest->route('submissionId'));
        $publication = Repo::publication()->get((int) $illuminateRequest->route('publicationId'));

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($submission->getId() !== $publication->getData('submissionId')) {
            return response()->json([
                'error' => __('api.publications.403.submissionsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Only accept publication props for relations
        $params = array_intersect_key($illuminateRequest->input(), array_flip(['relationStatus', 'vorDoi']));

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_PUBLICATION, $params);

        // Required in this handler
        if (!isset($params['relationStatus'])) {
            return response()->json([
                'relationStatus' => [__('validator.filled')],
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate against the schema
        $submissionContext = $request->getContext();
        if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
            $submissionContext = app()->get('context')->get($submission->getData('contextId'));
        }

        $errors = Repo::publication()->validate($publication, $params, $submission, $submissionContext);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        Repo::publication()->relate(
            $publication,
            $params['relationStatus'],
            $params['vorDoi'] ?? ''
        );

        $publication = Repo::publication()->get($publication->getId());

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByContextIds([$submission->getData('contextId')])
            ->getMany();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($request->getContext()->getId())->toArray();

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            Response::HTTP_OK
        );
    }
}
