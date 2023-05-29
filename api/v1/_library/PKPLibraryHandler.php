<?php

/**
 * @file api/v1/_library/PKPLibraryHandler.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2003-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPLibraryHandler
 *
 * @ingroup api_v1_announcement
 *
 * @brief Handle API requests for announcement operations.
 *
 */

namespace PKP\API\v1\_library;

use APP\core\Application;
use APP\core\Services;
use APP\file\LibraryFileManager;
use PKP\context\LibraryFile;
use PKP\context\LibraryFileDAO;
use PKP\core\APIResponse;
use PKP\db\DAORegistry;
use PKP\handler\APIHandler;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\SubmissionAccessPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use Psr\Http\Message\ServerRequestInterface;

class PKPLibraryHandler extends APIHandler
{
    public function __construct()
    {
        $this->_handlerPath = '_library';
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getLibrary'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
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
     *
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function getLibrary(ServerRequestInterface $slimRequest, APIResponse $response, array $args)
    {
        /** @var LibraryFileDAO $libraryFileDao */
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
        $submission = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
        $context = $this->getRequest()->getContext();
        $contextId = $context->getId();
        $libraryFileManager = new LibraryFileManager($contextId);

        $files = [];

        $params = $slimRequest->getQueryParams();
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

        return $response->withJson([
            'items' => $files,
            'itemsMax' => count($files),
        ], 200);
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
            'documentType' => Services::get('file')->getDocumentType($file->getFileType()),
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
