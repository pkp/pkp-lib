<?php
/**
 * @file pages/libraryFiles/LibraryFileHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class LibraryFileHandler
 * @ingroup pages_libraryFiles
 *
 * @brief Class defining a handler for library file access
 */

// Import the base handler.
import('classes.handler.Handler');

class LibraryFileHandler extends Handler {

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
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');

		$publicFileId = $args[0];

		$libraryFile = $libraryFileDao->getById($publicFileId, $context->getId());
		if ($libraryFile && $libraryFile->getPublicAccess()) {
			$filePath = $libraryFileManager->getBasePath() .  $libraryFile->getOriginalFileName();
			$libraryFileManager->downloadFile($filePath);
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
		$libraryFileDao = DAORegistry::getDAO('LibraryFileDAO');
		$libraryFile = $libraryFileDao->getById($request->getUserVar('libraryFileId'), $context->getId());
		if ($libraryFile) {

			// If this file has a submission ID, ensure that the current
			// user has access to that submission.
			if ($libraryFile->getSubmissionId()) {
				$allowedAccess = false;

				// Managers are always allowed access.
				$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
				if (array_intersect($userRoles, array(ROLE_ID_MANAGER))) $allowedAccess = true;

				// Check for specific assignments.
				$user = $request->getUser();
				$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO');
				$assignedUsers = $userStageAssignmentDao->getUsersBySubmissionAndStageId($libraryFile->getSubmissionId(), WORKFLOW_STAGE_ID_SUBMISSION);
				if (!$assignedUsers->wasEmpty()) {
					while ($assignedUser = $assignedUsers->next()) {
						if ($assignedUser->getId()  == $user->getId()) {
							$allowedAccess = true;
							break;
						}
					}
				}
			} else {
				$allowedAccess = true; // this is a Context submission document, default to access policy.
			}

			if ($allowedAccess) {
				$filePath = $libraryFileManager->getBasePath() .  $libraryFile->getOriginalFileName();
				$libraryFileManager->downloadFile($filePath);
			} else {
				header('HTTP/1.0 403 Forbidden');
				echo '403 Forbidden<br>';
				return;
			}
		}
	}
}
