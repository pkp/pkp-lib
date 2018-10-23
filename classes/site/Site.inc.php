<?php

/**
 * @defgroup site Site
 * Site-related concerns such as the Site object and version management.
 */

/**
 * @file classes/site/Site.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Site
 * @ingroup site
 * @see SiteDAO
 *
 * @brief Describes system-wide site properties.
 */


class Site extends DataObject {

	/**
	 * Return associative array of all locales supported by the site.
	 * These locales are used to provide a language toggle on the main site pages.
	 * @return array
	 */
	function &getSupportedLocaleNames() {
		$supportedLocales =& Registry::get('siteSupportedLocales', true, null);

		if ($supportedLocales === null) {
			$supportedLocales = array();
			$localeNames =& AppLocale::getAllLocales();

			$locales = $this->getSupportedLocales();
			foreach ($locales as $localeKey) {
				$supportedLocales[$localeKey] = $localeNames[$localeKey];
			}

			asort($supportedLocales);
		}

		return $supportedLocales;
	}

	//
	// Get/set methods
	//

	/**
	 * Get site title.
	 * @param $locale string Locale code to return, if desired.
	 */
	function getTitle($locale = null) {
		return $this->getData('title', $locale);
	}

	/**
	 * Get localized site title.
	 */
	function getLocalizedTitle() {
		return $this->getLocalizedData('title');
	}

	/**
	 * Get "localized" site page title (if applicable).
	 * @return string
	 */
	function getLocalizedPageHeaderTitle() {
		if ($this->getLocalizedData('pageHeaderTitleImage')) {
			return $this->getLocalizedData('pageHeaderTitleImage');
		}
		if ($this->getData('pageHeaderTitleImage', AppLocale::getPrimaryLocale())) {
			return $this->getData('pageHeaderTitleImage', AppLocale::getPrimaryLocale());
		}
		if ($this->getLocalizedData('title')) {
			return $this->getLocalizedData('title');
		}
		if ($this->getData('title', AppLocale::getPrimaryLocale())) {
			return $this->getData('title', AppLocale::getPrimaryLocale());
		}
		return '';
	}

	/**
	 * Get redirect
	 * @return int
	 */
	function getRedirect() {
		return $this->getData('redirect');
	}

	/**
	 * Set redirect
	 * @param $redirect int
	 */
	function setRedirect($redirect) {
		$this->setData('redirect', (int)$redirect);
	}

	/**
	 * Get localized site about statement.
	 */
	function getLocalizedAbout() {
		return $this->getLocalizedData('about');
	}

	/**
	 * Get localized site contact name.
	 */
	function getLocalizedContactName() {
		return $this->getLocalizedData('contactName');
	}

	/**
	 * Get localized site contact email.
	 */
	function getLocalizedContactEmail() {
		return $this->getLocalizedData('contactEmail');
	}

	/**
	 * Get minimum password length.
	 * @return int
	 */
	function getMinPasswordLength() {
		return $this->getData('minPasswordLength');
	}

	/**
	 * Set minimum password length.
	 * @param $minPasswordLength int
	 */
	function setMinPasswordLength($minPasswordLength) {
		$this->setData('minPasswordLength', $minPasswordLength);
	}

	/**
	 * Get primary locale.
	 * @return string
	 */
	function getPrimaryLocale() {
		return $this->getData('primaryLocale');
	}

	/**
	 * Set primary locale.
	 * @param $primaryLocale string
	 */
	function setPrimaryLocale($primaryLocale) {
		$this->setData('primaryLocale', $primaryLocale);
	}

	/**
	 * Get installed locales.
	 * @return array
	 */
	function getInstalledLocales() {
		$locales = $this->getData('installedLocales');
		return isset($locales) ? $locales : array();
	}

	/**
	 * Set installed locales.
	 * @param $installedLocales array
	 */
	function setInstalledLocales($installedLocales) {
		$this->setData('installedLocales', $installedLocales);
	}

	/**
	 * Get array of all supported locales (for static text).
	 * @return array
	 */
	function getSupportedLocales() {
		$locales = $this->getData('supportedLocales');
		return isset($locales) ? $locales : array();
	}

	/**
	 * Set array of all supported locales (for static text).
	 * @param $supportedLocales array
	 */
	function setSupportedLocales($supportedLocales) {
		$this->setData('supportedLocales', $supportedLocales);
	}
}


