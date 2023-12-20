<?php

/**
 * @file api/v1/jats/PKPJatsController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPJatsController
 *
 * @ingroup api_v1_jats
 *
 * @brief Handle API requests for JATS File operations.
 *
 */

namespace PKP\API\v1\jats;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\internal\SubmissionFileStageAccessPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\SubmissionFileAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submissionFile\SubmissionFile;

class PKPJatsController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/publications/{publicationId}/jats';
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

    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ]),
        ])->group(function () {

            Route::get('', $this->get(...))
                ->name('publication.jats.get');

            Route::post('', $this->add(...))
                ->name('publication.jats.add');

            Route::delete('', $this->delete(...))
                ->name('publication.jats.delete');

        })->whereNumber(['submissionId', 'publicationId']);
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

        if ($actionName === 'get') {
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        }

        if ($actionName === 'add') {
            $params = $illuminateRequest->input();
            $fileStage = isset($params['fileStage']) ? (int) $params['fileStage'] : SubmissionFile::SUBMISSION_FILE_JATS;
            $this->addPolicy(
                new SubmissionFileStageAccessPolicy(
                    $fileStage,
                    SubmissionFileAccessPolicy::SUBMISSION_FILE_ACCESS_MODIFY,
                    'api.submissionFiles.403.unauthorizedFileStageIdWrite'
                )
            );
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get JATS XML Files
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $context = Application::get()->getRequest()->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $jatsFile = Repo::jats()
            ->getJatsFile($publication->getId(), $submission->getId(), $genres->toArray());

        $jatsFilesProp = Repo::jats()
            ->summarize($jatsFile);

        return response()->json($jatsFilesProp, Response::HTTP_OK);
    }

    /**
     * Add a JATS XML Submission File to a publication
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        if (empty($_FILES)) {
            return response()->json([
                'error' => __('api.files.400.noUpload'),
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            return $this->getUploadErrorResponse($_FILES['file']['error']);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION_FILE, $illuminateRequest->input());

        Repo::jats()
            ->addJatsFile(
                $_FILES['file']['tmp_name'],
                $_FILES['file']['name'],
                $publication->getId(),
                $submission->getId(),
                SubmissionFile::SUBMISSION_FILE_JATS,
                $params);

        $context = Application::get()->getRequest()->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $jatsFile = Repo::jats()
            ->getJatsFile($publication->getId(), $submission->getId(), $genres->toArray());

        $jatsFilesProp = Repo::jats()
            ->summarize($jatsFile);
        
        return response()->json($jatsFilesProp, Response::HTTP_OK);
    }

    /**
     * Delete the publication's JATS Submission file
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $context = Application::get()->getRequest()->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $jatsFile = Repo::jats()
            ->getJatsFile($publication->getId(), $submission->getId(), $genres->toArray());
        
        if (!$jatsFile->submissionFile) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        Repo::submissionFile()
            ->delete($jatsFile->submissionFile);

        $jatsFile = Repo::jats()
            ->getJatsFile($publication->getId(), $submission->getId(), $genres->toArray());

        $jatsFilesProp = Repo::jats()
            ->summarize($jatsFile);
        
        return response()->json($jatsFilesProp, Response::HTTP_OK);
    }
}
