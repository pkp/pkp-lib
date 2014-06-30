<?php

/**
 * @file classes/plugin/GalleryPlugin.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class GalleryPlugin
 * @ingroup plugins
 *
 * @brief Class describing a plugin in the Plugin Gallery.
 */

class GalleryPlugin extends DataObject {
	/**
	 * Constructor
	 */
	function GalleryPlugin() {
		parent::DataObject();
	}

	/**
	 * Get the localized name of the context
	 * @param $preferredLocale string
	 * @return string
	 */
	function getLocalizedName($preferredLocale = null) {
		return $this->getLocalizedData('name', $preferredLocale);
	}

	/**
	 * Set the name of the context
	 * @param $name string
	 * @param $locale string optional
	 */
	function setName($name, $locale = null) {
		$this->setData('name', $name, $locale);
	}

	/**
	 * Get the name of the context
	 * @param $locale string optional
	 * @return string
	 */
	function getName($locale = null) {
		return $this->getData('name', $locale);
	}

	/**
	 * Get the homepage for this context
	 * @return string
	 */
	function getHomepage() {
		return $this->getData('homepage');
	}

	/**
	 * Set the homepage for this context
	 * @param $homepage string
	 */
	function setHomepage($homepage) {
		$this->setData('homepage', $homepage);
	}

	/**
	 * Get the contact name for this context
	 * @return string
	 */
	function getContactName() {
		return $this->getData('contactName');
	}

	/**
	 * Set the contact name for this context
	 * @param $contactName string
	 */
	function setContactName($contactName) {
		$this->setData('contactName', $contactName);
	}

	/**
	 * Get the contact institution name for this context
	 * @return string
	 */
	function getContactInstitutionName() {
		return $this->getData('contactInstitutionName');
	}

	/**
	 * Set the contact institution name for this context
	 * @param $contactInstitutionName string
	 */
	function setContactInstitutionName($contactInstitutionName) {
		$this->setData('contactInstitutionName', $contactInstitutionName);
	}

	/**
	 * Get the contact email for this context
	 * @return string
	 */
	function getContactEmail() {
		return $this->getData('contactEmail');
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
	 * Get the localized description of the context.
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedData('description');
	}
}

?>
