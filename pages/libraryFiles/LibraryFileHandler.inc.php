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

class LibraryFileHandler extends Handler
{
    /** @var Handler the Handler that calls the LibraryFileHandler functions */
    public $_callingHandler = null;

    /**
     * Constructor.
     *
     * @param $callingHandler Handler
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
     * @param $args array
     * @param $request Request
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
     * @param $args array
     * @param $request Request
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
                    if (array_intersect($userRoles, [ROLE_ID_MANAGER])) {
                        $allowedAccess = true;
                    }
                }

                // Check for specific assignments.
                $user = $request->getUser();
                $userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /** @var UserStageAssignmentDAO $userStageAssignmentDao */
                $assignedUsers = $userStageAssignmentDao->getUsersBySubmissionAndStageId($libraryFile->getSubmissionId(), WORKFLOW_STAGE_ID_SUBMISSION);
                while ($assignedUser = $assignedUsers->next()) {
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
