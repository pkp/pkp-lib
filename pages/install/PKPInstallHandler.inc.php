<?php

/**
 * @file PKPInstallHandler.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
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
	 * If no context is selected, list all.
	 * Otherwise, display the index page for the selected context.
	 */
	function index() {
		// Make sure errors are displayed to the browser during install.
		@ini_set('display_errors', true);

		$this->validate();
		$this->setupTemplate();

		if (($setLocale = PKPRequest::getUserVar('setLocale')) != null && AppLocale::isLocaleValid($setLocale)) {
			Request::setCookieVar('currentLocale', $setLocale);
		}

		if (checkPhpVersion('5.0.0')) { // WARNING: This form needs $this in constructor
			$installForm = new InstallForm();
		} else {
			$installForm =& new InstallForm();
		}
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

		if (checkPhpVersion('5.0.0')) { // WARNING: This form needs $this in constructor
			$installForm = new InstallForm();
		} else {
			$installForm =& new InstallForm();
		}
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

		if (($setLocale = PKPRequest::getUserVar('setLocale')) != null && AppLocale::isLocaleValid($setLocale)) {
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
		$this->validate();
		$this->setupTemplate();

		$installForm = new UpgradeForm();
		$installForm->readInputData();

		if ($installForm->validate()) {
			$installForm->execute();
		} else {
			$installForm->display();
		}
	}

	function setupTemplate() {
		parent::setupTemplate();
		AppLocale::requireComponents(array(LOCALE_COMPONENT_PKP_INSTALLER));
	}
}

?>
