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

use APP\components\forms\publication\IssueEntryForm;
use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\publication\Publication;
use APP\submission\Submission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\components\forms\publication\TitleAbstractForm;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\submission\GenreDAO;
use PKP\userGroup\UserGroup;

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
        $this->requiresSubmissionAccess[] = 'getPublicationIssueForm';
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

        Route::middleware([
            self::roleAuthorizer([Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_ASSISTANT]),
        ])->group(function () {
            Route::prefix('{submissionId}/publications/{publicationId}/_components')->group(function () {
                Route::get('issue', $this->getPublicationIssueForm(...))->name('submission.publication._components.issue');
            })->whereNumber(['submissionId', 'publicationId']);
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

        $userGroups = UserGroup::withContextIds([[$submission->getData('contextId')]])
            ->get();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($request->getContext()->getId())->toArray();

        return response()->json(
            Repo::publication()->getSchemaMap($submission, $userGroups, $genres)->map($publication),
            Response::HTTP_OK
        );
    }

    /**
     * @copydoc \PKP\api\v1\submissions\PKPSubmissionController::getPublicationTitleAbstractForm()
     */
    protected function getPublicationTitleAbstractForm(Request $illuminateRequest): JsonResponse
    {
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $submission = $data['submission']; /** @var Submission $submission */
        $publication = $data['publication']; /** @var Publication $publication*/
        $context = $data['context']; /** @var Context $context*/
        $publicationApiUrl = $data['publicationApiUrl']; /** @var String $publicationApiUrl*/

        $section = Repo::section()->get($publication->getData('sectionId'), $context->getId());
        $locales = $this->getPublicationFormLocales($context, $submission);
        $submissionLocale = $submission->getData('locale');

        $titleAbstract = new TitleAbstractForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $section->getData('wordCount'),
            !$section->getData('abstractsNotRequired')
        );
        return response()->json($this->getLocalizedForm($titleAbstract, $submissionLocale, $locales), Response::HTTP_OK);
    }

    /**
     * Get IssueEntryForm form component
     */
    protected function getPublicationIssueForm(Request $illuminateRequest): JsonResponse
    {
        $data = $this->getSubmissionAndPublicationData($illuminateRequest);

        if (isset($data['error'])) {
            return response()->json([ 'error' => $data['error'],], $data['status']);
        }

        $request = $this->getRequest();
        $submission = $data['submission']; /** @var Submission $submission */
        $publication = $data['publication']; /** @var Publication $publication*/
        $context = $data['context']; /** @var Context $context*/
        $publicationApiUrl = $data['publicationApiUrl']; /** @var String $publicationApiUrl*/
        $locales = $this->getPublicationFormLocales($context, $submission);
        $temporaryFileApiUrl = $request->getDispatcher()->url($request, Application::ROUTE_API, $context->getPath(), 'temporaryFiles');

        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId());

        // This form provides Issue details for a submission's publication.
        // This includes fields to change the Issue and section that the submission the publication is linked to, cover image, page and publication date details.
        $issueEntryForm = new IssueEntryForm(
            $publicationApiUrl,
            $locales,
            $publication,
            $context,
            $baseUrl,
            $temporaryFileApiUrl
        );

        return response()->json($this->getLocalizedForm($issueEntryForm, $submission->getData('locale'), $locales), Response::HTTP_OK);
    }
}
