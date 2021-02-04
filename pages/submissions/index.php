<?php
/**
 * @defgroup pages_submissions Submissions editorial page
 */

 /**
 * @file lib/pkp/pages/submissions/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_submissions
 * @brief Handle requests for submissions functions.
 *
 */

switch($op) {
	case 'index':
	case 'tasks':
		define('HANDLER_CLASS', 'DashboardHandler');
		import('lib.pkp.pages.dashboard.DashboardHandler');
		break;
}


