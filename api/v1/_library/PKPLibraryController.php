<?php

/**
 * @file api/v1/_library/PKPLibraryController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLibraryController
 *
 * @ingroup api_v1__library
 *
 * @brief Controller class to handle API requests for library operations.
 *
 */

namespace PKP\API\v1\_library;

use APP\core\Application;
use APP\file\LibraryFileManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\context\LibraryFile;
use PKP\context\LibraryFileDAO;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPLibraryController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_library';
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
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->getLibrary(...))->name('_library.getLibrary');
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        if ($request->getUserVar('includeSubmissionId')) {
            $this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments, 'includeSubmissionId'));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a list of all files in the library
     */
    public function getLibrary(Request $illuminateRequest): JsonResponse
    {
        /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $context = $this->getRequest()->getContext();
        $contextId = $context->getId();
        $libraryFileManager = new LibraryFileManager($contextId);

        $files = [];

        $params = $illuminateRequest->query();
        if (isset($params['includeSubmissionId'])) {
            $result = $libraryFileDao->getBySubmissionId($submission->getId());
            while ($file = $result->next()) {
                $files[] = $this->fileToResponse($file, $libraryFileManager);
            }
        }

        $result = $libraryFileDao->getByContextId($contextId);
        while ($file = $result->next()) {
            $files[] = $this->fileToResponse($file, $libraryFileManager);
        }

        return response()->json([
            'items' => $files,
            'itemsMax' => count($files),
        ], Response::HTTP_OK);
    }

    /**
     * Convert a file object to the JSON response object
     */
    protected function fileToResponse(LibraryFile $file, LibraryFileManager $libraryFileManager): array
    {
        $request = Application::get()->getRequest();

        $urlArgs = [
            'libraryFileId' => $file->getId(),
        ];
        if ($file->getSubmissionId()) {
            $urlArgs['submissionId'] = $file->getSubmissionId();
        }

        return [
            'id' => $file->getId(),
            'filename' => $file->getServerFileName(),
            'name' => $file->getName(null),
            'mimetype' => $file->getFileType(),
            'documentType' => app()->get('file')->getDocumentType($file->getFileType()),
            'submissionId' => $file->getSubmissionId() ?? 0,
            'type' => $file->getType(),
            'typeName' => __($libraryFileManager->getTitleKeyFromType($file->getType())),
            'url' => $request->getDispatcher()->url(
                $request,
                Application::ROUTE_COMPONENT,
                null,
                'api.file.FileApiHandler',
                'downloadLibraryFile',
                null,
                $urlArgs
            ),
        ];
    }
}
