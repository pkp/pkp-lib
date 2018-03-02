<?php
/**
 * @file pages/publicLibraryFiles/PublicLibraryFileHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PublicLibraryFileHandler
 * @ingroup pages_publicLibraryFiles
 *
 * @brief Class defining a handler for public library file access
 */

// Import the base handler.
import('classes.handler.Handler');

class PublicLibraryFileHandler extends Handler {

	//
	// Public handler methods
	//

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

		$publicFileId = $args[0];

		$libraryFile = $libraryFileDao->getById($publicFileId);
		if ($libraryFile && $libraryFile->getPublicAccess() && $libraryFile->getContextId() == $context->getId()) {
			$filePath = $libraryFileManager->getBasePath() .  $libraryFile->getOriginalFileName();
			$libraryFileManager->downloadFile($filePath);
		} else {
				header('HTTP/1.0 403 Forbidden');
				echo '403 Forbidden<br>';
				return;
		}
	}
}
