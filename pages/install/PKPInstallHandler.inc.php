<?php

/**
 * @file PKPInstallHandler.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPInstallHandler
 * @ingroup pages_install
 *
 * @brief Handle installation requests. 
 */


/* FIXME Prevent classes from trying to initialize the session manager (and thus the database connection) */
define('SESSION_DISABLE_INIT', 1);

import('install.form.InstallForm');
import('install.form.UpgradeForm');
import('core.PKPHandler');

class PKPInstallHandler extends PKPHandler {

	/**
	 * If no journal is selected, display list of journals.
	 * Otherwise, display the index page for the selected journal.
	 */
	function index() {
		// Make sure errors are displayed to the browser during install.
		@ini_set('display_errors', E_ALL);

		PKPInstallHandler::validate();
		PKPInstallHandler::setupTemplate();

		if (($setLocale = PKPRequest::getUserVar('setLocale')) != null && Locale::isLocaleValid($setLocale)) {
			Request::setCookieVar('currentLocale', $setLocale);
		}

		$installForm = new InstallForm();
		$installForm->initData();
		$installForm->display();
	}

	/**
	 * Redirect to index if system has already been installed.
	 */
	function validate() {
		if (Config::getVar('general', 'installed')) {
			PKPRequest::redirect(null, 'index');	
		}
	}

	/**
	 * Execute installer.
	 */
	function install() {
		PKPInstallHandler::validate();
		PKPInstallHandler::setupTemplate();

		$installForm = new InstallForm();
		$installForm->readInputData();

		if ($installForm->validate()) {
			$installForm->execute();

		} else {
			$installForm->display();
		}
	}

	/**
	 * Display upgrade form.
	 */
	function upgrade() {
		PKPInstallHandler::validate();
		PKPInstallHandler::setupTemplate();

		if (($setLocale = PKPRequest::getUserVar('setLocale')) != null && Locale::isLocaleValid($setLocale)) {
			PKPRequest::setCookieVar('currentLocale', $setLocale);
		}

		$installForm = new UpgradeForm();
		$installForm->initData();
		$installForm->display();
	}

	/**
	 * Execute upgrade.
	 */
	function installUpgrade() {
		PKPInstallHandler::validate();

		$installForm = new UpgradeForm();
		$installForm->readInputData();

		if ($installForm->validate()) {
			$installForm->execute();

		} else {
			PKPInstallHandler::setupTemplate();
			$installForm->display();
		}
	}

	function setupTemplate() {
		parent::setupTemplate();
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_INSTALLER));
	}
}

?>
