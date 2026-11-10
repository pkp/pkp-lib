<?php

/**
 * @file pages/libraryFiles/LibraryFileHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileHandler
 *
 * @ingroup pages_libraryFiles
 *
 * @brief Class defining a handler for library file access
 */

namespace PKP\pages\libraryFiles;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\file\LibraryFileManager;
use APP\handler\Handler;
use PKP\context\LibraryFileDAO;
use PKP\db\DAORegistry;
use PKP\security\Role;

class LibraryFileHandler extends Handler
{
    //
    // Public handler methods
    //

    /**
     * Download a library public file.
     *
     * @param array $args
     * @param Request $request
     */
    public function downloadPublic($args, $request)
    {
        $context = $request->getContext();
        $libraryFileManager = new LibraryFileManager($context->getId());
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */

        $publicFileId = $args[0];

        $libraryFile = $libraryFileDao->getById($publicFileId, $context->getId());
        if ($libraryFile && $libraryFile->getPublicAccess()) {
            $libraryFileManager->downloadByPath($libraryFile->getFilePath(), null, true);
        } else {
            header('HTTP/1.0 403 Forbidden');
            echo '403 Forbidden<br>';
            return;
        }
    }
}
