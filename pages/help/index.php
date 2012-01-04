<?php

/**
 * @defgroup pages_help
 */

/**
 * @file pages/help/index.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_help
 * @brief Handle requests for viewing help pages.
 *
 */

// $Id$


switch ($op) {
	case 'index':
	case 'toc':
	case 'view':
	case 'search':
		define('HANDLER_CLASS', 'HelpHandler');
		import('pages.help.HelpHandler');
		break;
}

?>
