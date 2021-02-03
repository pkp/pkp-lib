<?php

/**
 * @file lib/pkp/pages/publicLibraryFiles/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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


