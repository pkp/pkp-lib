<?php

/**
 * @file plugins/generic/shibboleth/pages/ShibbolethHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2003-2017 John Willinsky
 * Distributed under the GNU GPL v2 or later. For full terms see the file docs/COPYING.
 *
 * @class ShibbolethHandler
 * @ingroup plugins_generic_shibboleth
 *
 * @brief Handle Shibboleth responses
 */

import('classes.handler.Handler');

class ShibbolethHandler extends Handler {
	/**
	 * Login handler
	 * 
	 * @param $args array
	 * @param $request Request
	 * @return bool
	 */
	function shibLogin($args, $request) {
		$plugin = $this->_getPlugin();
		$contextId = $plugin->getCurrentContextId();
		$uin_header = $plugin->getSetting($contextId, 'shibbolethHeaderUin');
		$email_header = $plugin->getSetting($contextId, 'shibbolethHeaderEmail');

		// We rely on these headers being present.
		if (!isset($_SERVER[$uin_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $uin_header");
			Validation::logout();
			Validation::redirectLogin();
		}
		if (!isset($_SERVER[$email_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $email_header");
			Validation::logout();
			Validation::redirectLogin();
		}

		$uin = $_SERVER[$uin_header];
		$user_email = $_SERVER[$email_header];

		// The UIN must be set; otherwise login failed.
		if ($uin == null) {
			Validation::logout();
			Validation::redirectLogin();
		}

		// Try to locate the user by UIN.
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& $userDao->getUserByAuthStr($uin, true);
		if (isset($user)) {
			syslog(LOG_INFO, "Shibboleth located returning user $uin");
		} else {
			// We use the e-mail as a key.
			$user =& $userDao->getUserByEmail($user_email);
		}

		if (isset($user)) {
			syslog(LOG_INFO,"Shibboleth located returning email $user_email");

			if ($user->getAuthStr() != "") {
				syslog(
					LOG_ERR,
					"Shibboleth user with email $user_email already has UID"
				);
				return false;
			}
		} else {
			// @@@ TODO register a new user
			return false;
		}

		if (isset($user)) {
			// @@@ TODO: admin privileges

			$disabledReason = null;
			Validation::registerUserSessions($user, $disabledReason);

			// @@@ TODO: check disabled status

			return $this->_redirectAfterLogin($request);
		}

		return false;
	}

	//
	// Private helper methods
	//
	/**
	 * Get the Shibboleth plugin object
	 * 
	 * @return ShibbolethAuthPlugin
	 */
	function &_getPlugin() {
		$plugin =& PluginRegistry::getPlugin('generic', SHIBBOLETH_PLUGIN_NAME);
		return $plugin;
	}

	/**
	 * @copydoc LoginHandler::_redirectAfterLogin
	 */
	function _redirectAfterLogin($request) {
		$context = $this->getTargetContext($request);
		// If there's a context, send them to the dashboard after login.
		if ($context && $request->getUserVar('source') == '' && array_intersect(
			array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT),
			(array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
		)) {
			return $request->redirect($context->getPath(), 'dashboard');
		}

		$request->redirectHome();
	}
}
?>
