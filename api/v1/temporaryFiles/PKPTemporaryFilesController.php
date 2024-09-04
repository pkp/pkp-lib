<?php
/**
 * @file api/v1/temporaryFiles/PKPTemporaryFilesController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryFilesController
 *
 * @ingroup api_v1_temporaryFiles
 *
 * @brief Controller class to handle API requests to upload a file and receive a temporary file ID.
 */

namespace PKP\API\v1\temporaryFiles;

use APP\core\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\file\TemporaryFileManager;
use PKP\middleware\AttachFileUploadHeader;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;

class PKPTemporaryFilesController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'temporaryFiles';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_ASSISTANT,
            ]),
            AttachFileUploadHeader::class,
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::options('', $this->getOptions(...))
            ->name('temporaryFile.getOptions');

        Route::post('', $this->uploadFile(...))
            ->name('temporaryFile.uploadFile');
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

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Upload a requested file
     */
    public function uploadFile(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        if (empty($_FILES)) {
            return response()->json([
                'error' => __('api.files.400.noUpload'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $temporaryFileManager = new TemporaryFileManager();
        $fileName = $temporaryFileManager->getFirstUploadedPostName();
        $uploadedFile = $temporaryFileManager->handleUpload($fileName, $request->getUser()->getId());

        if ($uploadedFile === false) {
            if ($temporaryFileManager->uploadError($fileName)) {
                switch ($temporaryFileManager->getUploadErrorCode($fileName)) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        return response()->json([
                            'error' => __('api.files.400.fileSize', ['maxSize' => Application::getReadableMaxFileSize()]),
                        ], Response::HTTP_BAD_REQUEST);
                    case UPLOAD_ERR_PARTIAL:
                        return response()->json([
                            'error' => __('api.files.400.uploadFailed'),
                        ], Response::HTTP_BAD_REQUEST);
                    case UPLOAD_ERR_NO_FILE:
                        return response()->json([
                            'error' => __('api.files.400.noUpload'),
                        ], Response::HTTP_BAD_REQUEST);
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                    case UPLOAD_ERR_EXTENSION:
                        return response()->json([
                            'error' => __('api.files.400.config'),
                        ], Response::HTTP_BAD_REQUEST);
                }
            }

            return response()->json([
                'error' => __('api.files.400.uploadFailed'),
            ], Response::HTTP_BAD_REQUEST);
        }

        return response()->json([
            'id' => $uploadedFile->getId(),
            'name' => $uploadedFile->getData('originalFileName'),
            'mimetype' => $uploadedFile->getData('filetype'),
            'documentType' => app()->get('file')->getDocumentType($uploadedFile->getData('filetype')),
        ], Response::HTTP_OK);
    }

    /**
     * Respond affirmatively to a HTTP OPTIONS request with headers which allow file uploads
     */
    public function getOptions(Request $illuminateRequest): JsonResponse
    {
        return response()->json([], Response::HTTP_OK);
    }
}
