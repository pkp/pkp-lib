<?php

/**
 * @defgroup pages_install Installation Pages
 */

/**
 * @file pages/install/index.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_install
 * @brief Handle installation requests.
 *
 */

switch ($op) {
	case 'index':
	case 'install':
	case 'upgrade':
	case 'installUpgrade':
		define('HANDLER_CLASS', 'InstallHandler');
		import('lib.pkp.pages.install.InstallHandler');
		break;
}


