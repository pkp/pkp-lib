<?php

/**
 * @defgroup pages_install
 */

/**
 * @file pages/install/index.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_install
 * @brief Handle installation requests.
 *
 */

// $Id$


switch ($op) {
	case 'index':
	case 'install':
	case 'upgrade':
	case 'installUpgrade':
		define('HANDLER_CLASS', 'PKPInstallHandler');
		import('pages.install.PKPInstallHandler');
		break;
}

?>
