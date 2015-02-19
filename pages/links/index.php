<?php

/**
 * @defgroup pages_links Link page
 */

/**
 * @file pages/links/index.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_links
 * @brief Handle site link page requests.
 *
 */


switch ($op) {
	case 'link':
		define('HANDLER_CLASS', 'LinksHandler');
		import('lib.pkp.pages.links.LinksHandler');
		break;
}

?>
