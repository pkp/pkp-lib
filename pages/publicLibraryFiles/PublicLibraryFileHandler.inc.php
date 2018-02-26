<?php
/**
 * @defgroup controllers_api_file File API controller
 */

/**
 * @file controllers/api/file/FileApiHandler.inc.php
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
	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		return parent::authorize($request, $args, $roleAssignments);
	}

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

		$requestPath = $request->getRequestPath();
		$parts = explode("/", $requestPath);

		// Check the last string of the request.
		// The code does not check the url format to be /publicLibrary/downloadLibraryFile/id
		$publicFileId = $parts[count($parts) - 1];

		$libraryFile = $libraryFileDao->getById($publicFileId);
		if ($libraryFile && $libraryFile->getPublicAccess()) {
			$filePath = $libraryFileManager->getBasePath() .  $libraryFile->getOriginalFileName();
			$libraryFileManager->downloadFile($filePath);
		} else {
			fatalError('Unauthorized access. This file is not public.');
		}
	}
}

?>
