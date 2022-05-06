<?php
/**
 * @file pages/libraryFiles/LibraryFileHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileHandler
 * @ingroup pages_libraryFiles
 *
 * @brief Class defining a handler for library file access
 */

use APP\file\LibraryFileManager;

use APP\handler\Handler;
use APP\facades\Repo;
use PKP\user\Collector;
use PKP\security\Role;

class LibraryFileHandler extends Handler
{
    /** @var Handler the Handler that calls the LibraryFileHandler functions */
    public $_callingHandler = null;

    /**
     * Constructor.
     *
     * @param Handler $callingHandler
     */
    public function __construct($callingHandler)
    {
        $this->_callingHandler = $callingHandler;
    }

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

    /**
     * Download a library file.
     *
     * @param array $args
     * @param Request $request
     */
    public function downloadLibraryFile($args, $request)
    {
        $context = $request->getContext();
        $libraryFileManager = new LibraryFileManager($context->getId());
        $libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /** @var LibraryFileDAO $libraryFileDao */
        $libraryFile = $libraryFileDao->getById($request->getUserVar('libraryFileId'), $context->getId());
        if ($libraryFile) {

            // If this file has a submission ID, ensure that the current
            // user has access to that submission.
            if ($libraryFile->getSubmissionId()) {
                $allowedAccess = false;

                // Managers are always allowed access.
                if ($this->_callingHandler) {
                    $userRoles = $this->_callingHandler->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
                    if (array_intersect($userRoles, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])) {
                        $allowedAccess = true;
                    }
                }

                // Check for specific assignments.
                $assignedUsers = Repo::user()->getMany(
                    Repo::user()->getCollector()
                    ->assignedTo($libraryFile->getSubmissionId(), WORKFLOW_STAGE_ID_SUBMISSION)
                );
                $user = $request->getUser();
                foreach ($assignedUsers as $assignedUser) {
                    if ($assignedUser->getId() == $user->getId()) {
                        $allowedAccess = true;
                        break;
                    }
                }
            } else {
                $allowedAccess = true; // this is a Context submission document, default to access policy.
            }

            if ($allowedAccess) {
                $libraryFileManager->downloadByPath($libraryFile->getFilePath());
            } else {
                header('HTTP/1.0 403 Forbidden');
                echo '403 Forbidden<br>';
                return;
            }
        }
    }
}
