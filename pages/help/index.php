<?php

/**
 * @defgroup pages_help
 */

/**
 * @file pages/help/index.php
 *
 * Copyright (c) 2013-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_help
 * @brief Handle requests for viewing help pages.
 *
 */

switch ($op) {
	case 'index':
	case 'toc':
	case 'view':
	case 'search':
		define('HANDLER_CLASS', 'HelpHandler');
		import('lib.pkp.pages.help.HelpHandler');
		break;
}

?>
