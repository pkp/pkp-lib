<?php

/**
 * @defgroup pages_help Help Pages
 */

/**
 * @file lib/pkp/pages/help/index.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_help
 * @brief Handle requests for help functions.
 *
 */

switch($op) {
	case 'index':
		define('HANDLER_CLASS', 'HelpHandler');
		import('lib.pkp.pages.help.HelpHandler');
		break;
}


