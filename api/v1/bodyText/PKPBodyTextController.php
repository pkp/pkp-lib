<?php

/**
 * @file api/v1/bodyText/PKPBodyTextController.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPBodyTextController
 *
 * @brief Handle API requests for body text file operations.
 *
 */

namespace PKP\API\v1\bodyText;

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

class PKPBodyTextController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'submissions/{submissionId}/publications/{publicationId}/bodyText';
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
                ->name('publication.bodyText.get');

            Route::put('', $this->save(...))
                ->name('publication.bodyText.save');

            Route::delete('', $this->delete(...))
                ->name('publication.bodyText.delete');

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

        if ($actionName === 'save') {
            $params = $illuminateRequest->input();
            $fileStage = isset($params['fileStage']) ? (int) $params['fileStage'] : SubmissionFile::SUBMISSION_FILE_BODY_TEXT;
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
     * Get body text files
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

        $bodyTextFile = Repo::bodyText()
            ->getBodyTextFile($publication->getId(), $submission->getId(), $genres->toArray());

        return response()->json(
            Repo::bodyText()->map($bodyTextFile),
            Response::HTTP_OK
        );
    }

    /**
     * Save body text content for a publication
     */
    public function save(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SUBMISSION_FILE, $illuminateRequest->input());

        Repo::bodyText()
            ->setBodyText(
                $_POST['bodyText'],
                $publication->getId(),
                $submission->getId(),
                SubmissionFile::SUBMISSION_FILE_BODY_TEXT,
                $params
            );

        $context = Application::get()->getRequest()->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $bodyTextFile = Repo::bodyText()
            ->getBodyTextFile($publication->getId(), $submission->getId(), $genres->toArray());

        return response()->json(
            Repo::bodyText()->map($bodyTextFile),
            Response::HTTP_OK
        );
    }

    /**
     * Delete the publication's body text file
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        $context = Application::get()->getRequest()->getContext();
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getEnabledByContextId($context->getId());

        $bodyTextFile = Repo::bodyText()
            ->getBodyTextFile($publication->getId(), $submission->getId(), $genres->toArray());

        if (!$bodyTextFile->submissionFile) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        Repo::submissionFile()
            ->delete($bodyTextFile->submissionFile);

        $bodyTextFile = Repo::bodyText()
            ->getBodyTextFile($publication->getId(), $submission->getId(), $genres->toArray());

        return response()->json(
            Repo::bodyText()->map($bodyTextFile),
            Response::HTTP_OK
        );
    }
}
