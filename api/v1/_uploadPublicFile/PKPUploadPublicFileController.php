<?php
/**
 * @file api/v1/uploadPublicFile/PKPUploadPublicFileController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPUploadPublicFileController
 *
 * @ingroup api_v1__uploadPublicFile
 *
 * @brief Controller class to handle API requests to upload a file to a user's public directory.
 */

namespace PKP\API\v1\_uploadPublicFile;

use APP\core\Application;
use FilesystemIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\config\Config;
use PKP\core\Core;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\core\PKPString;
use PKP\file\FileManager;
use PKP\middleware\AttachFileUploadHeader;
use PKP\plugins\Hook;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PKPUploadPublicFileController extends PKPBaseController
{
    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return '_uploadPublicFile';
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
                Role::ROLE_ID_REVIEWER,
                Role::ROLE_ID_AUTHOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_READER
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
            ->name('_uploadPublicFile.getOptions');

        Route::post('', $this->uploadFile(...))
            ->name('_uploadPublicFile.uploadFile');
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
     *
     * @hook API::uploadPublicFile::permissions [[ &$userDir, &$isUserAllowed, &$allowedDirSize, &$allowedFileTypes, $request, $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES), ]]
     */
    public function uploadFile(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        if (empty($_FILES) || empty($_FILES['file'])) {
            return response()->json([
                'error' => __('api.files.400.noUpload'),
            ], Response::HTTP_BAD_REQUEST);
        }

        $siteDir = Core::getBaseDir() . '/' . Config::getVar('files', 'public_files_dir') . '/site';

        if (!file_exists($siteDir) || !is_writeable($siteDir)) {
            return response()->json([
                'error' => __('api.publicFiles.500.badFilesDir'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $userDir = $siteDir . '/images/' . $request->getUser()->getUsername();
        $isUserAllowed = true;
        $allowedDirSize = Config::getVar('files', 'public_user_dir_size', 5000) * 1024;
        $allowedFileTypes = ['gif', 'jpg', 'png', 'webp'];

        Hook::call('API::uploadPublicFile::permissions', [
            &$userDir,
            &$isUserAllowed,
            &$allowedDirSize,
            &$allowedFileTypes,
            $request,
            $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES),
        ]);

        // Allow plugins to control who can upload files
        if (!$isUserAllowed) {
            return response()->json([
                'error' => __('api.publicFiles.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Don't allow user to exceed the alotted space in their public directory
        $currentSize = 0;
        if ($allowedDirSize > 0 && file_exists($userDir)) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($userDir, FilesystemIterator::SKIP_DOTS)) as $object) {
                $currentSize += $object->getSize();
            }
        }
        if (($currentSize + $_FILES['file']['size']) > $allowedDirSize) {
            return response()->json([
                'error' => __('api.publicFiles.413.noDirSpace', [
                    'fileUploadSize' => ceil($_FILES['file']['size'] / 1024),
                    'dirSizeLeft' => ceil(($allowedDirSize - $currentSize) / 1024),
                ]),
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $fileManager = new FileManager();
        $filename = $fileManager->getUploadedFileName('file');
        $filename = trim(
            preg_replace(
                "/[^a-z0-9\.\-]+/",
                '',
                str_replace(
                    [' ', '_', ':'],
                    '-',
                    strtolower($filename)
                )
            )
        );
        $extension = pathinfo(strtolower(trim($filename)), PATHINFO_EXTENSION);

        // Only allow permitted file types
        if (!in_array($extension, $allowedFileTypes)) {
            return response()->json([
                'error' => __('api.publicFiles.400.extensionNotSupported', [
                    'fileTypes' => join(__('common.commaListSeparator'), $allowedFileTypes),
                ]),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Perform additional checks on images
        if (in_array($extension, ['gif', 'jpg', 'jpeg', 'png', 'jpe'])) {
            if (getimagesize($_FILES['file']['tmp_name']) === false) {
                return response()->json([
                    'error' => __('api.publicFiles.400.invalidImage'),
                ], Response::HTTP_BAD_REQUEST);
            }
            $extensionFromMimeType = $fileManager->getImageExtension(PKPString::mime_content_type($_FILES['file']['tmp_name']));
            if ($extensionFromMimeType !== '.' . $extension) {
                return response()->json([
                    'error' => __('api.publicFiles.400.mimeTypeNotMatched'),
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Save the file
        $destinationPath = $this->_getFilename($siteDir . '/images/' . $request->getUser()->getUsername() . '/' . $filename, $fileManager);
        $success = $fileManager->uploadFile('file', $destinationPath);

        if ($success === false) {
            if ($fileManager->uploadError($filename)) {
                switch ($fileManager->getUploadErrorCode($filename)) {
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
            'url' => $request->getBaseUrl() . '/' .
                    Config::getVar('files', 'public_files_dir') . '/site/images/' .
                    $request->getUser()->getUsername() . '/' .
                    pathinfo($destinationPath, PATHINFO_BASENAME),
        ], Response::HTTP_OK);
    }

    /**
     * Respond affirmatively to a HTTP OPTIONS request with headers which allow
     * file uploads
     */
    public function getOptions(Request $illuminateRequest): JsonResponse
    {
        return response()->json([], Response::HTTP_OK);
    }

    /**
     * A recursive function to get a filename that will not overwrite an
     * existing file
     *
     * @param string        $path           Preferred filename
     *
     */
    private function _getFilename(string $path, FileManager $fileManager): string
    {
        if ($fileManager->fileExists($path)) {
            $pathParts = pathinfo($path);
            $filename = $pathParts['filename'] . '-' . md5(microtime()) . '.' . $pathParts['extension'];

            if (strlen($filename > 255)) {
                $filename = substr($filename, -255, 255);
            }

            return $this->_getFilename($pathParts['dirname'] . '/' . $filename, $fileManager);
        }

        return $path;
    }
}
