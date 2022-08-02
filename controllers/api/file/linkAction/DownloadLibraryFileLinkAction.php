<?php
/**
 * @file controllers/api/file/linkAction/DownloadLibraryFileLinkAction.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DownloadLibraryFileLinkAction
 * @ingroup controllers_api_file_linkAction
 *
 * @brief An action to download a library file.
 */

namespace PKP\controllers\api\file\linkAction;

use PKP\linkAction\LinkAction;
use PKP\linkAction\request\PostAndRedirectAction;

class DownloadLibraryFileLinkAction extends LinkAction
{
    /**
     * Constructor
     *
     * @param Request $request
     * @param LibraryFile $libraryFile the library file to
     *  link to.
     */
    public function __construct($request, $libraryFile)
    {
        // Instantiate the redirect action request.
        $router = $request->getRouter();
        $redirectRequest = new PostAndRedirectAction(
            $router->url(
                $request,
                null,
                'api.file.FileApiHandler',
                'enableLinkAction',
                null,
                $this->getActionArgs($libraryFile)
            ),
            $router->url(
                $request,
                null,
                'api.file.FileApiHandler',
                'downloadLibraryFile',
                null,
                $this->getActionArgs($libraryFile)
            )
        );

        // Configure the file link action.
        parent::__construct(
            'downloadFile',
            $redirectRequest,
            htmlspecialchars($libraryFile->getLocalizedName()),
            $libraryFile->getDocumentType()
        );
    }

    /**
     * Return the action arguments to address a file.
     *
     * @param LibraryFile $libraryFile
     *
     * @return array
     */
    public function getActionArgs(&$libraryFile)
    {
        assert($libraryFile instanceof \PKP\context\LibraryFile);

        // Create the action arguments array.
        $args = ['libraryFileId' => $libraryFile->getId()];

        if ($libraryFile->getSubmissionId()) {
            $args['submissionId'] = $libraryFile->getSubmissionId();
        }

        return $args;
    }
}
