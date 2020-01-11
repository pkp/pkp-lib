<?php

/**
 * @defgroup pages_navigationMenu NavigationMenu Pages
 */

/**
 * @file lib/pkp/pages/navigationMenu/index.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_navigationMenu
 * @brief Handle requests for NavigationMenus functions.
 *
 */

switch ($op) {
	case 'index':
	case 'view':
	case 'preview':
		define('HANDLER_CLASS', 'NavigationMenuItemHandler');
		import('lib.pkp.pages.navigationMenu.NavigationMenuItemHandler');
		break;
}


