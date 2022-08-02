<?php
/**
 * @file api/v1/contexts/PKPTemporaryFilesHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPTemporaryFilesHandler
 * @ingroup api_v1_users
 *
 * @brief Handle API requests to upload a file and receive a temporary file ID.
 */

namespace PKP\API\v1\contexts;

use APP\core\Application;
use APP\core\Services;
use PKP\file\TemporaryFileManager;
use PKP\handler\APIHandler;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\Role;

class PKPTemporaryFilesHandler extends APIHandler
{
    /**
     * @copydoc APIHandler::__construct()
     */
    public function __construct()
    {
        $this->_handlerPath = 'temporaryFiles';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_ASSISTANT];
        $this->_endpoints = [
            'OPTIONS' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getOptions'],
                    'roles' => $roles,
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'uploadFile'],
                    'roles' => $roles,
                ],
            ],
        ];

        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * A helper method which adds the necessary response headers to allow
     * file uploads
     *
     * @param Response $response object
     *
     * @return Response
     */
    private function getResponse($response)
    {
        return $response->withHeader('Access-Control-Allow-Headers', 'Content-Type, X-Requested-With, X-PINGOTHER, X-File-Name, Cache-Control');
    }

    /**
     * Upload a requested file
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function uploadFile($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        if (empty($_FILES)) {
            return $response->withStatus(400)->withJsonError('api.files.400.noUpload');
        }

        $temporaryFileManager = new TemporaryFileManager();
        $fileName = $temporaryFileManager->getFirstUploadedPostName();
        $uploadedFile = $temporaryFileManager->handleUpload($fileName, $request->getUser()->getId());

        if ($uploadedFile === false) {
            if ($temporaryFileManager->uploadError($fileName)) {
                switch ($temporaryFileManager->getUploadErrorCode($fileName)) {
                    case UPLOAD_ERR_INI_SIZE:
                    case UPLOAD_ERR_FORM_SIZE:
                        return $response->withStatus(400)->withJsonError('api.files.400.fileSize', ['maxSize' => Application::getReadableMaxFileSize()]);
                    case UPLOAD_ERR_PARTIAL:
                        return $response->withStatus(400)->withJsonError('api.files.400.uploadFailed');
                    case UPLOAD_ERR_NO_FILE:
                        return $response->withStatus(400)->withJsonError('api.files.400.noUpload');
                    case UPLOAD_ERR_NO_TMP_DIR:
                    case UPLOAD_ERR_CANT_WRITE:
                    case UPLOAD_ERR_EXTENSION:
                        return $response->withStatus(400)->withJsonError('api.files.400.config');
                }
            }
            return $response->withStatus(400)->withJsonError('api.files.400.uploadFailed');
        }

        return $this->getResponse($response->withJson([
            'id' => $uploadedFile->getId(),
            'name' => $uploadedFile->getData('originalFileName'),
            'mimetype' => $uploadedFile->getData('filetype'),
            'documentType' => Services::get('file')->getDocumentType($uploadedFile->getData('filetype')),
        ]));
    }

    /**
     * Respond affirmatively to a HTTP OPTIONS request with headers which allow
     * file uploads
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getOptions($slimRequest, $response, $args)
    {
        return $this->getResponse($response);
    }
}
