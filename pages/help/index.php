<?php

/**
 * @defgroup pages_help
 */

/**
 * @file pages/help/index.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_help
 * @brief Handle requests for viewing help pages.
 *
 */

// $Id: index.php,v 1.4 2009/12/10 00:57:04 asmecher Exp $


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
