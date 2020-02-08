<?php

/**
 * @defgroup pages_user User Pages
 */

/**
 * @file lib/pkp/pages/user/index.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @ingroup pages_user
 * @brief Handle requests for user functions.
 *
 */

switch ($op) {
	//
	// Profiles
	//
	case 'profile':
		define('HANDLER_CLASS', 'ProfileHandler');
		import('lib.pkp.pages.user.ProfileHandler');
		break;
	//
	// Registration
	//
	case 'register':
	case 'registerUser':
	case 'activateUser':
		define('HANDLER_CLASS', 'RegistrationHandler');
		import('lib.pkp.pages.user.RegistrationHandler');
		break;
}


