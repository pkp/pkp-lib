<?php

/**
 * @file PKPInstallHandler.inc.php
 *
 * Copyright (c) 2000-2009 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPInstallHandler
 * @ingroup pages_install
 *
 * @brief Handle installation requests.
 */


import('install.form.InstallForm');
import('install.form.UpgradeForm');
import('handler.Handler');

class PKPInstallHandler extends Handler {

	/**
	 * If no journal is selected, display list of journals.
	 * Otherwise, display the index page for the selected journal.
	 */
	function index() {
		// Make sure errors are displayed to the browser during install.
		@ini_set('display_errors', E_ALL);

		$this->validate();
		$this->setupTemplate();

		if (($setLocale = PKPRequest::getUserVar('setLocale')) != null && Locale::isLocaleValid($setLocale)) {
			Request::setCookieVar('currentLocale', $setLocale);
		}

		// FIXME: Need construction by reference or validation always fails on PHP 4.x
		$installForm =& new InstallForm();
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
		$this->validate();
		$this->setupTemplate();

		// FIXME: Need construction by reference or validation always fails on PHP 4.x
		$installForm =& new InstallForm();
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
		$this->validate();
		$this->setupTemplate();

		if (($setLocale = PKPRequest::getUserVar('setLocale')) != null && Locale::isLocaleValid($setLocale)) {
			PKPRequest::setCookieVar('currentLocale', $setLocale);
		}

		// FIXME: Need construction by reference or validation always fails on PHP 4.x
		$installForm =& new UpgradeForm();
		$installForm->initData();
		$installForm->display();
	}

	/**
	 * Execute upgrade.
	 */
	function installUpgrade() {
		$this->validate();

		$installForm =& new UpgradeForm();
		$installForm->readInputData();

		if ($installForm->validate()) {
			$installForm->execute();

		} else {
			$this->setupTemplate();
			$installForm->display();
		}
	}

	function setupTemplate() {
		parent::setupTemplate();
		Locale::requireComponents(array(LOCALE_COMPONENT_PKP_INSTALLER));
	}
}

?>
