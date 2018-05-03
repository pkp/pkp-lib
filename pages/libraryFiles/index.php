<?php

/**
 * @file lib/pkp/pages/publicLibraryFiles/index.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_publicLibraryFiles
 * @brief Handle requests for public library files.
 *
 */

switch ($op) {
	case 'downloadPublic':
	case 'downloadLibraryFile':
		define('HANDLER_CLASS', 'LibraryFileHandler');
		import('lib.pkp.pages.libraryFiles.LibraryFileHandler');
		break;
}

?>
