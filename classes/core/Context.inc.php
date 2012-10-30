<?php

/**
 * @file classes/core/Context.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
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
	 * Get the name of the press
	 * @return string
	 */
	function getLocalizedName() {
		return $this->getLocalizedSetting('name');
	}

	/**
	 * Set the name of the press
	 * @param $pressName string
	 */
	function setName($name) {
		$this->setData('name', $name);
	}

	/**
	 * get the name of the press
	 */
	function getName($locale) {
		return $this->getSetting('name', $locale);
	}

	/**
	 * Get the contact name for this press
	 * @return string
	 */
	function getContactName() {
		return $this->getSetting('contactName');
	}

	/**
	 * Set the contact name for this press
	 * @param $contactName string
	 */
	function setContactName($contactName) {
		$this->setData('contactName', $contactName);
	}

	/**
	 * Get the contact email for this press
	 * @return string
	 */
	function getContactEmail() {
		return $this->getSetting('contactEmail');
	}

	/**
	 * Set the contact email for this press
	 * @param $contactEmail string
	 */
	function setContactEmail($contactEmail) {
		$this->setData('contactEmail', $contactEmail);
	}

	/**
	 * Get press description.
	 * @return string
	 */
	function getDescription() {
		return $this->getData('description');
	}

	/**
	 * Set announcement description.
	 * @param $description string
	 * @param $locale string
	 */
	function setDescription($description) {
		$this->setData('description', $description);
	}

	/**
	 * Get path to press (in URL).
	 * @return string
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Set path to press (in URL).
	 * @param $path string
	 */
	function setPath($path) {
		return $this->setData('path', $path);
	}

	/**
	 * Get enabled flag of press
	 * @return int
	 */
	function getEnabled() {
		return $this->getData('enabled');
	}

	/**
	 * Set enabled flag of press
	 * @param $enabled int
	 */
	function setEnabled($enabled) {
		return $this->setData('enabled',$enabled);
	}

	/**
	 * Return the primary locale of this press.
	 * @return string
	 */
	function getPrimaryLocale() {
		return $this->getData('primaryLocale');
	}

	/**
	 * Set the primary locale of this press.
	 * @param $locale string
	 */
	function setPrimaryLocale($primaryLocale) {
		return $this->setData('primaryLocale', $primaryLocale);
	}
	/**
	 * Get sequence of press in site table of contents.
	 * @return float
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of press in site table of contents.
	 * @param $sequence float
	 */
	function setSequence($sequence) {
		return $this->setData('sequence', $sequence);
	}

	/**
	 * Get the localized description of the press.
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedSetting('description');
	}

	/**
	 * Return associative array of all locales supported by forms on the site.
	 * These locales are used to provide a language toggle on the main site pages.
	 * @return array
	 */
	function &getSupportedFormLocaleNames() {
		$supportedLocales =& $this->getData('supportedFormLocales');

		if (!isset($supportedLocales)) {
			$supportedLocales = array();
			$localeNames =& AppLocale::getAllLocales();

			$locales = $this->getSetting('supportedFormLocales');
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
	 * Return associative array of all locales supported by the site.
	 * These locales are used to provide a language toggle on the main site pages.
	 * @return array
	 */
	function &getSupportedLocaleNames() {
		$supportedLocales =& $this->getData('supportedLocales');

		if (!isset($supportedLocales)) {
			$supportedLocales = array();
			$localeNames =& AppLocale::getAllLocales();

			$locales = $this->getSetting('supportedLocales');
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
}

?>
