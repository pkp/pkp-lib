<?php

/**
 * @defgroup pages_admin Administration Pages
 */

/**
 * @file lib/pkp/pages/admin/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_admin
 * @brief Handle requests for site administration functions.
 *
 */

switch ($op) {
	//
	// Administrative functions
	//
	case 'systemInfo':
	case 'phpinfo':
	case 'expireSessions':
	case 'clearTemplateCache':
	case 'clearDataCache':
	case 'downloadScheduledTaskLogFile':
	case 'clearScheduledTaskLogFiles':
		define('HANDLER_CLASS', 'AdminFunctionsHandler');
		import('lib.pkp.pages.admin.AdminFunctionsHandler');
		break;
	//
	// Main administration pages
	//
	case 'index':
	case 'contexts':
	case 'settings':
	case 'saveSettings':
	case 'wizard':
		define('HANDLER_CLASS', 'AdminHandler');
		import('lib.pkp.pages.admin.AdminHandler');
		break;
}
