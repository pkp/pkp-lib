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
	/** @var ShibbolethAuthPlugin */
	var $_plugin;

	/** @var int */
	var $_contextId;

	/**
	 * Login handler
	 * 
	 * @param $args array
	 * @param $request Request
	 * @return bool
	 */
	function shibLogin($args, $request) {
		$this->_plugin = $this->_getPlugin();
		$this->_contextId = $this->_plugin->getCurrentContextId();
		$uin_header = $this->_plugin->getSetting(
			$this->_contextId,
			'shibbolethHeaderUin'
		);
		$email_header = $this->_plugin->getSetting(
			$this->_contextId,
			'shibbolethHeaderEmail'
		);

		// We rely on these headers being present.
		if (!isset($_SERVER[$uin_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $uin_header");
			Validation::logout();
			Validation::redirectLogin();
			return false;
		}
		if (!isset($_SERVER[$email_header])) {
			syslog(LOG_ERR, "Shibboleth plugin enabled, but not properly configured; failed to find $email_header");
			Validation::logout();
			Validation::redirectLogin();
			return false;
		}

		$uin = $_SERVER[$uin_header];
		$user_email = $_SERVER[$email_header];

		// The UIN must be set; otherwise login failed.
		if ($uin == null) {
			Validation::logout();
			Validation::redirectLogin();
			return false;
		}

		// Try to locate the user by UIN.
		$userDao =& DAORegistry::getDAO('UserDAO');
		$user =& $userDao->getUserByAuthStr($uin, true);
		if (isset($user)) {
			syslog(LOG_INFO, "Shibboleth located returning user $uin");
		} else {
			// We use the e-mail as a key.
			$user =& $userDao->getUserByEmail($user_email);

			if (isset($user)) {
				syslog(LOG_INFO, "Shibboleth located returning email $user_email");

				if ($user->getAuthStr() != "") {
					syslog(
						LOG_ERR,
						"Shibboleth user with email $user_email already has UID"
					);
					Validation::logout();
					Validation::redirectLogin();
					return false;
				} else {
					$user->setAuthStr($uin);
					$userDao->updateObject($user);
				}
			} else {
				// @@@ TODO register a new user
				return false;
			}
		}

		if (isset($user)) {
			$this->_checkAdminStatus($user);

			$disabledReason = null;
			$success = Validation::registerUserSession($user, $disabledReason);

			if (!$success) {
				// @@@ TODO: present user with disabled reason
				syslog(
					LOG_ERR,
					"Disabled user $uin attempted Shibboleth login" .
						($disabledReason == null ? "" : ": $disabledReason")
				);
				Validation::logout();
				Validation::redirectLogin();
				return false;
			}

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
	 * Check if the user should be an admin according to the
	 * Shibboleth plugin settings, and adjust the User object
	 * accordingly.
	 * 
	 * @param $user User
	 */
	function _checkAdminStatus($user) {
		$adminsStr = $this->_plugin->getSetting(
			$this->_contextId,
			'shibbolethAdminUins'
		);
		$admins = explode(' ', $adminsStr);

		$uin = $user->getAuthStr();
		if ($uin == null || $uin == "") {
			return;
		}

		$userId = $user->getId();
		$adminFound = array_search($uin, $admins);

		$userGroupDao =& DAORegistry::getDAO('UserGroupDAO');

		// should be unique
		$adminGroup = $userGroupDao->getByRoleId(0, ROLE_ID_SITE_ADMIN)->next();
		$adminId = $adminGroup->getId();


		// If they are in the list of users who should be admins
		if ($adminFound !== false) {
			// and if they are not already an admin
			if(!$userGroupDao->userInGroup($userId, $adminId)) {
				syslog(LOG_INFO, "Shibboleth assigning admin to $uin");
				$userGroupDao->assignUserToGroup($userId, $adminId);
			}
		} else {
			// If they are not in the admin list - then be sure they
			// are not an admin in the role table
			syslog(LOG_ERR, "removing admin for $uin");
			$userGroupDao->removeUserFromGroup($userId, $adminId, 0);
		}
	}

	/**
	 * @copydoc LoginHandler::_redirectAfterLogin
	 */
	function _redirectAfterLogin($request) {
		$context = $this->getTargetContext($request);
		// If there's a context, send them to the dashboard after login.
		if ($context && $request->getUserVar('source') == '' &&
			array_intersect(
				array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT),
				(array) $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
			)) {
			return $request->redirect($context->getPath(), 'dashboard');
		}

		return $request->redirectHome();
	}
}
?>
