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
use PKP\bodyText\ConversionException;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\authorization\PublicationAccessPolicy;
use PKP\security\authorization\PublicationWritePolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;

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
        // Read access: authors may view body text content (read-only)
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

        })->whereNumber(['submissionId', 'publicationId']);

        // Write access: body text is a production artifact editable by editorial roles only (no author)
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
            ]),
        ])->group(function () {

            Route::put('', $this->save(...))
                ->name('publication.bodyText.save');

            Route::post('', $this->createHtmlFile(...))
                ->name('publication.bodyText.createHtmlFile');

            Route::post('pdf', $this->createPdfGalley(...))
                ->name('publication.bodyText.createPdfGalley');

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
        } elseif (in_array($actionName, ['createHtmlFile', 'createPdfGalley'], true)) {
            // Align permissions for galleys with the ArticleGalleyGridHandler
            $this->addPolicy(new WorkflowStageAccessPolicy(
                $request,
                $args,
                $roleAssignments,
                'submissionId',
                WORKFLOW_STAGE_ID_PRODUCTION
            ));
            $this->addPolicy(new PublicationAccessPolicy($request, $args, $roleAssignments));
        } else {
            $this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get body text content for a publication
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

        $bodyTextFile = Repo::bodyText()->getBodyTextFile($publication->getId());

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

        Repo::bodyText()->setBodyText(
            $_POST['bodyText'],
            $publication->getId(),
        );

        $bodyTextFile = Repo::bodyText()->getBodyTextFile($publication->getId());

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

        $bodyTextFile = Repo::bodyText()->getBodyTextFile($publication->getId());

        if (!$bodyTextFile->submissionFile) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        Repo::submissionFile()->delete($bodyTextFile->submissionFile);

        $bodyTextFile = Repo::bodyText()->getBodyTextFile($publication->getId());

        return response()->json(
            Repo::bodyText()->map($bodyTextFile),
            Response::HTTP_OK
        );
    }

    /**
     * Creates a PDF from body text and imports it as a galley
     */
    public function createPdfGalley(Request $illuminateRequest): JsonResponse
    {
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($illuminateRequest->getContentTypeFormat() !== 'pdf') {
            return response()->json([
                'error' => __('api.400.invalidValue', ['param' => 'Content-Type']),
            ], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $pdf = $illuminateRequest->getContent();
        if (!str_starts_with($pdf, PKPString::PDF_HEADER)) {
            return response()->json([
                'error' => __('api.400.invalidValue', ['param' => 'body']),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $galley = Repo::bodyText()->createPdfGalley($pdf, $publication->getId());
        } catch (\Throwable $exception) {
            return $this->conversionErrorResponse($exception, $publication->getId());
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        return response()->json(
            Repo::galley()->getSchemaMap($submission, $publication, [])->map($galley),
            Response::HTTP_CREATED
        );
    }

    /**
     * Create an HTML galley without changing the editor's JSON document.
     */
    public function createHtmlFile(Request $illuminateRequest): JsonResponse
    {
        $publication = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_PUBLICATION);

        if (!$publication) {
            return response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        if ($illuminateRequest->getContentTypeFormat() !== 'html') {
            return response()->json([
                'error' => __('api.400.invalidValue', ['param' => 'Content-Type']),
            ], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $html = $illuminateRequest->getContent();
        if (trim($html) === '') {
            return response()->json([
                'error' => __('api.400.invalidValue', ['param' => 'body']),
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $galley = Repo::bodyText()->createHtmlFile($html, $publication->getId());
        } catch (\Throwable $exception) {
            return $this->conversionErrorResponse($exception, $publication->getId());
        }

        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);

        return response()->json(
            Repo::galley()->getSchemaMap($submission, $publication, [])->map($galley),
            Response::HTTP_CREATED
        );
    }

    /**
     * Handle exceptions specific to body text conversion.
     */
    protected function conversionErrorResponse(\Throwable $exception, int $publicationId): JsonResponse
    {
        $translationKey = $exception instanceof ConversionException
            ? match ($exception->reason) {
                ConversionException::INVALID_GALLEY_FILE => 'publication.bodyText.error.invalidGalleyFile',
                ConversionException::IMAGE_NOT_ATTACHED => 'publication.bodyText.error.imageNotAttached',
                ConversionException::IMAGE_NOT_EXPORTABLE => 'publication.bodyText.error.imageNotExportable',
                default => null,
            }
        : null;

        if ($translationKey !== null) {
            return response()->json([
                'error' => __($translationKey),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        error_log('Unable to export body text for publication ' . $publicationId . ': ' . $exception);

        return response()->json([
            'error' => __('api.422.routeRequestUnableToProcess'),
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
