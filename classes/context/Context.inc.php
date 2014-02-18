<?php

/**
 * @file classes/context/Context.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Context
 * @ingroup core
 *
 * @brief Basic class describing a context.
 */

class Context extends DataObject {
	/**
	 * Constructor
	 */
	function Context() {
		parent::DataObject();
	}

	/**
	 * Get the localized name of the context
	 * @param $preferredLocale string
	 * @return string
	 */
	function getLocalizedName($preferredLocale = null) {
		return $this->getLocalizedSetting('name', $preferredLocale);
	}

	/**
	 * Set the name of the context
	 * @param $name string
	 */
	function setName($name, $locale = null) {
		$this->setData('name', $name, $locale);
	}

	/**
	 * get the name of the context
	 */
	function getName($locale = null) {
		return $this->getSetting('name', $locale);
	}

	/**
	 * Get the contact name for this context
	 * @return string
	 */
	function getContactName() {
		return $this->getSetting('contactName');
	}

	/**
	 * Set the contact name for this context
	 * @param $contactName string
	 */
	function setContactName($contactName) {
		$this->setData('contactName', $contactName);
	}

	/**
	 * Get the contact email for this context
	 * @return string
	 */
	function getContactEmail() {
		return $this->getSetting('contactEmail');
	}

	/**
	 * Set the contact email for this context
	 * @param $contactEmail string
	 */
	function setContactEmail($contactEmail) {
		$this->setData('contactEmail', $contactEmail);
	}

	/**
	 * Get context description.
	 * @param $description string optional
	 * @return string
	 */
	function getDescription($locale = null) {
		return $this->getData('description', $locale);
	}

	/**
	 * Set context description.
	 * @param $description string
	 * @param $locale string optional
	 */
	function setDescription($description, $locale = null) {
		$this->setData('description', $description, $locale);
	}

	/**
	 * Get path to context (in URL).
	 * @return string
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Set path to context (in URL).
	 * @param $path string
	 */
	function setPath($path) {
		return $this->setData('path', $path);
	}

	/**
	 * Get enabled flag of context
	 * @return int
	 */
	function getEnabled() {
		return $this->getData('enabled');
	}

	/**
	 * Set enabled flag of context
	 * @param $enabled int
	 */
	function setEnabled($enabled) {
		return $this->setData('enabled', $enabled);
	}

	/**
	 * Return the primary locale of this context.
	 * @return string
	 */
	function getPrimaryLocale() {
		return $this->getData('primaryLocale');
	}

	/**
	 * Set the primary locale of this context.
	 * @param $locale string
	 */
	function setPrimaryLocale($primaryLocale) {
		return $this->setData('primaryLocale', $primaryLocale);
	}
	/**
	 * Get sequence of context in site-wide list.
	 * @return float
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of context in site table of contents.
	 * @param $sequence float
	 */
	function setSequence($sequence) {
		return $this->setData('sequence', $sequence);
	}

	/**
	 * Get the localized description of the context.
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedSetting('description');
	}

	/**
	 * Get localized acronym of context
	 * @return string
	 */
	function getLocalizedAcronym() {
		return $this->getLocalizedSetting('acronym');
	}

	/**
	 * Get the acronym of the context.
	 * @param $locale string
	 * @return string
	 */
	function getAcronym($locale) {
		return $this->getSetting('acronym', $locale);
	}

	/**
	 * Get the supported form locales.
	 * @return array
	 */
	function getSupportedFormLocales() {
		return $this->getSetting('supportedFormLocales');
	}

	/**
	 * Return associative array of all locales supported by forms on the site.
	 * These locales are used to provide a language toggle on the main site pages.
	 * @return array
	 */
	function getSupportedFormLocaleNames() {
		$supportedLocales =& $this->getData('supportedFormLocaleNames');

		if (!isset($supportedLocales)) {
			$supportedLocales = array();
			$localeNames =& AppLocale::getAllLocales();

			$locales = $this->getSupportedFormLocales();
			if (!isset($locales) || !is_array($locales)) {
				$locales = array();
			}

			foreach ($locales as $localeKey) {
				$supportedLocales[$localeKey] = $localeNames[$localeKey];
			}
		}

		return $supportedLocales;
	}

	/**
	 * Get the supported submission locales.
	 * @return array
	 */
	function getSupportedSubmissionLocales() {
		return $this->getSetting('supportedSubmissionLocales');
	}

	/**
	 * Return associative array of all locales supported by submissions on the
	 * site. These locales are used to provide a language toggle on the main
	 * site pages.
	 * @return array
	 */
	function getSupportedSubmissionLocaleNames() {
		$supportedLocales =& $this->getData('supportedSubmissionLocaleNames');

		if (!isset($supportedLocales)) {
			$supportedLocales = array();
			$localeNames =& AppLocale::getAllLocales();

			$locales = $this->getSupportedSubmissionLocales();
			if (!isset($locales) || !is_array($locales)) {
				$locales = array();
			}

			foreach ($locales as $localeKey) {
				$supportedLocales[$localeKey] = $localeNames[$localeKey];
			}
		}

		return $supportedLocales;
	}

	/**
	 * Get the supported locales.
	 * @return array
	 */
	function getSupportedLocales() {
		return $this->getSetting('supportedLocales');
	}

	/**
	 * Return associative array of all locales supported by the site.
	 * These locales are used to provide a language toggle on the main site pages.
	 * @return array
	 */
	function getSupportedLocaleNames() {
		$supportedLocales =& $this->getData('supportedLocaleNames');

		if (!isset($supportedLocales)) {
			$supportedLocales = array();
			$localeNames =& AppLocale::getAllLocales();

			$locales = $this->getSupportedLocales();
			if (!isset($locales) || !is_array($locales)) {
				$locales = array();
			}

			foreach ($locales as $localeKey) {
				$supportedLocales[$localeKey] = $localeNames[$localeKey];
			}
		}

		return $supportedLocales;
	}

	/**
	 * Get the association type for this context.
	 * @return int
	 */
	function getAssocType() {
		assert(false); // Must be overridden by subclasses
	}

	/**
	 * Get the settings DAO for this context object.
	 * @return DAO
	 */
	static function getSettingsDAO() {
		assert(false); // Must be implemented by subclasses
	}

	/**
	 * Get the DAO for this context object.
	 * @return DAO
	 */
	static function getDAO() {
		assert(false); // Must be implemented by subclasses
	}

	/**
	 * Retrieve array of settings.
	 * @return array
	 */
	function &getSettings() {
		$settingsDao = $this->getSettingsDAO();
		$settings =& $settingsDao->getSettings($this->getId());
		return $settings;
	}

	/**
	 * Retrieve a context setting value.
	 * @param $name string
	 * @param $locale string
	 * @return mixed
	 */
	function &getSetting($name, $locale = null) {
		$settingsDao = $this->getSettingsDAO();
		$setting =& $settingsDao->getSetting($this->getId(), $name, $locale);
		return $setting;
	}

	/**
	 * Update a context setting value.
	 * @param $name string
	 * @param $value mixed
	 * @param $type string optional
	 * @param $isLocalized boolean optional
	 */
	function updateSetting($name, $value, $type = null, $isLocalized = false) {
		$settingsDao = $this->getSettingsDAO();
		return $settingsDao->updateSetting($this->getId(), $name, $value, $type, $isLocalized);
	}

	/**
	 * Get a localized context setting by name.
	 * @param $name string
	 * @return mixed
	 */
	function &getLocalizedSetting($name) {
		$returner = $this->getSetting($name, AppLocale::getLocale());
		if ($returner === null) {
			$returner = $this->getSetting($name, AppLocale::getPrimaryLocale());
		}
		return $returner;
	}
}

?>
