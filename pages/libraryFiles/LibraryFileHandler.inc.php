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

// Import the base handler.
import('classes.handler.Handler');

class LibraryFileHandler extends Handler {

	/** @var Handler the Handler that calls the LibraryFileHandler functions */
	var $_callingHandler = null;

	/**
	 * Constructor.
	 * @param $callingHandler Handler
	 */
	function __construct($callingHandler) {
		$this->_callingHandler = $callingHandler;
	}

	//
	// Public handler methods
	//

	/**
	 * Download a library public file.
	 * @param $args array
	 * @param $request Request
	 */
	function downloadPublic($args, $request) {
		import('classes.file.LibraryFileManager');
		$context = $request->getContext();
		$libraryFileManager = new LibraryFileManager($context->getId());
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */

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
	 * @param $args array
	 * @param $request Request
	 */
	function downloadLibraryFile($args, $request) {
		import('classes.file.LibraryFileManager');
		$context = $request->getContext();
		$libraryFileManager = new LibraryFileManager($context->getId());
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO'); /* @var $libraryFileDao LibraryFileDAO */
		$libraryFile = $libraryFileDao->getById($request->getUserVar('libraryFileId'), $context->getId());
		if ($libraryFile) {

			// If this file has a submission ID, ensure that the current
			// user has access to that submission.
			if ($libraryFile->getSubmissionId()) {
				$allowedAccess = false;

				// Managers are always allowed access.
				if ($this->_callingHandler instanceof PKPHandler) {
					$userRoles = $this->_callingHandler->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
					if (array_intersect($userRoles, array(ROLE_ID_MANAGER))) $allowedAccess = true;
				}

				// Check for specific assignments.
				$user = $request->getUser();
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
				$assignedUsers = $userStageAssignmentDao->getUsersBySubmissionAndStageId($libraryFile->getSubmissionId(), WORKFLOW_STAGE_ID_SUBMISSION);
				while ($assignedUser = $assignedUsers->next()) {
					if ($user && $assignedUser->getId() == $user->getId()) {
						$allowedAccess = true;
						break;
					}
				}
			} else {
				// this is a Context submission document; rely on the calling handler's access policy, but ensure there is one.
				// (The page router constructs page handlers with the Request, so a truthy check is not enough.)
				$allowedAccess = $this->_callingHandler instanceof PKPHandler;
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
