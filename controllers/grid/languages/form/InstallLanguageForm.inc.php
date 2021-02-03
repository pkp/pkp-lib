<?php

/**
 * @file controllers/grid/languages/form/InstallLanguageForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallLanguageForm
 * @ingroup controllers_grid_languages_form
 *
 * @brief Form for installing languages.
 */

// Import the base Form.
import('lib.pkp.classes.form.Form');

class InstallLanguageForm extends Form {

	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct('controllers/grid/languages/installLanguageForm.tpl');
	}

	//
	// Overridden methods from Form.
	//
	/**
	 * @copydoc Form::initData()
	 */
	function initData() {
		parent::initData();

		$request = Application::get()->getRequest();
		$site = $request->getSite();
		$this->setData('installedLocales', $site->getInstalledLocales());
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		$site = $request->getSite();
		$allLocales = AppLocale::getAllLocales();
		$installedLocales = $this->getData('installedLocales');
		$notInstalledLocales = array_diff(array_keys($allLocales), $installedLocales);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign(array(
			'allLocales' => $allLocales,
			'notInstalledLocales' => $notInstalledLocales,
		));

		return parent::fetch($request, $template, $display);
	}

	/**
	 * @copydoc Form::readInputData()
	 */
	function readInputData() {
		parent::readInputData();

		$request = Application::get()->getRequest();
		$localesToInstall = $request->getUserVar('localesToInstall');
		$this->setData('localesToInstall', $localesToInstall);
	}

	/**
	 * @copydoc Form::execute()
	 */
	function execute(...$functionArgs) {
		$request = Application::get()->getRequest();
		$site = $request->getSite();
		$localesToInstall = $this->getData('localesToInstall');

		parent::execute(...$functionArgs);

		if (isset($localesToInstall) && is_array($localesToInstall)) {
			$installedLocales = $site->getInstalledLocales();
			$supportedLocales = $site->getSupportedLocales();

			foreach ($localesToInstall as $locale) {
				if (AppLocale::isLocaleValid($locale) && !in_array($locale, $installedLocales)) {
					array_push($installedLocales, $locale);
					// Activate/support by default.
					if (!in_array($locale, $supportedLocales)) array_push($supportedLocales, $locale);
					AppLocale::installLocale($locale);
				}
			}

			$site->setInstalledLocales($installedLocales);
			$site->setSupportedLocales($supportedLocales);
			$siteDao = DAORegistry::getDAO('SiteDAO'); /* @var $siteDao SiteDAO */
			$siteDao->updateObject($site);
		}
	}
}
