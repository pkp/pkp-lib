<?php

/**
 * @defgroup site
 */

/**
 * @file classes/site/Site.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Site
 * @ingroup site
 * @see SiteDAO
 *
 * @brief Describes system-wide site properties.
 */

// $Id$


class Site extends DataObject {
	/**
	 * Constructor.
	 */
	function Site() {
		parent::DataObject();
	}

	/**
	 * Return associative array of all locales supported by the site.
	 * These locales are used to provide a language toggle on the main site pages.
	 * @return array
	 */
	function &getSupportedLocaleNames() {
		$supportedLocales =& Registry::get('siteSupportedLocales', true, null);

		if ($supportedLocales === null) {
			$supportedLocales = array();
			$localeNames =& Locale::getAllLocales();

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
	 * Get localized site title.
	 */
	function getSiteTitle() {
		return $this->getLocalizedSetting('title');
	}

	/**
	 * Get "localized" site page title (if applicable).
	 * @return string
	 */
	function getSitePageHeaderTitle() {
		$typeArray = $this->getData('pageHeaderTitleType');
		$imageArray = $this->getData('pageHeaderTitleImage');
		$titleArray = $this->getData('title');

		$title = null;

		foreach (array(Locale::getLocale(), Locale::getPrimaryLocale()) as $locale) {
			if (isset($typeArray[$locale]) && $typeArray[$locale]) {
				if (isset($imageArray[$locale])) $title = $imageArray[$locale];
			}
			if (empty($title) && isset($titleArray[$locale])) $title = $titleArray[$locale];
			if (!empty($title)) return $title;
		}
		return null;
	}

	/**
	 * Get localized site logo type.
	 * @return boolean
	 */
	function getSitePageHeaderTitleType() {
		return $this->getLocalizedData('pageHeaderTitleType');
	}

	/**
	 * Get original site stylesheet filename.
	 * @return string
	 */
	function getOriginalStyleFilename() {
		return $this->getData('originalStyleFilename');
	}

	/**
	 * Set original site stylesheet filename.
	 * @param $originalStyleFilename string
	 */
	function setOriginalStyleFilename($originalStyleFilename) {
		return $this->setData('originalStyleFilename', $originalStyleFilename);
	}

	/**
	 * Get localized site intro.
	 */
	function getSiteIntro() {
		return $this->getLocalizedSetting('intro');
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
		return $this->setData('redirect', (int)$redirect);
	}

	/**
	 * Get localized site about statement.
	 */
	function getSiteAbout() {
		return $this->getLocalizedSetting('about');
	}

	/**
	 * Get localized site contact name.
	 */
	function getSiteContactName() {
		return $this->getLocalizedSetting('contactName');
	}

	/**
	 * Get localized site contact email.
	 */
	function getSiteContactEmail() {
		return $this->getLocalizedSetting('contactEmail');
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
		return $this->setData('minPasswordLength', $minPasswordLength);
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
		return $this->setData('primaryLocale', $primaryLocale);
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
		return $this->setData('installedLocales', $installedLocales);
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
		return $this->setData('supportedLocales', $supportedLocales);
	}

	/**
	 * Get the local name under which the site-wide locale file is stored.
	 * @return string
	 */
	function getSiteStyleFilename() {
		return 'sitestyle.css';
	}

	/**
	 * Retrieve a site setting value.
	 * @param $name string
	 * @param $locale string
	 * @return mixed
	 */
	function &getSetting($name, $locale = null) {
		$siteSettingsDao =& DAORegistry::getDAO('SiteSettingsDAO');
		$setting =& $siteSettingsDao->getSetting($name, $locale);
		return $setting;
	}

	function getLocalizedSetting($name) {
		$returner = $this->getSetting($name, Locale::getLocale());
		if ($returner === null) {
			unset($returner);
			$returner = $this->getSetting($name, Locale::getPrimaryLocale());
		}
		return $returner;
	}

	/**
	 * Update a site setting value.
	 * @param $name string
	 * @param $value mixed
	 * @param $type string optional
	 * @param $isLocalized boolean optional
	 */
	function updateSetting($name, $value, $type = null, $isLocalized = false) {
		$siteSettingsDao =& DAORegistry::getDAO('SiteSettingsDAO');
		return $siteSettingsDao->updateSetting($name, $value, $type, $isLocalized);
	}
}

?>
