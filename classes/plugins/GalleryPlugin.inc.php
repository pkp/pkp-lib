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
	 * Get the localized name of the plugin
	 * @param $preferredLocale string
	 * @return string
	 */
	function getLocalizedName($preferredLocale = null) {
		return $this->getLocalizedData('name', $preferredLocale);
	}

	/**
	 * Set the name of the plugin
	 * @param $name string
	 * @param $locale string optional
	 */
	function setName($name, $locale = null) {
		$this->setData('name', $name, $locale);
	}

	/**
	 * Get the name of the plugin
	 * @param $locale string optional
	 * @return string
	 */
	function getName($locale = null) {
		return $this->getData('name', $locale);
	}

	/**
	 * Get the homepage for this plugin
	 * @return string
	 */
	function getHomepage() {
		return $this->getData('homepage');
	}

	/**
	 * Set the homepage for this plugin
	 * @param $homepage string
	 */
	function setHomepage($homepage) {
		$this->setData('homepage', $homepage);
	}

	/**
	 * Get the product (symbolic name) for this plugin
	 * @return string
	 */
	function getProduct() {
		return $this->getData('product');
	}

	/**
	 * Set the product (symbolic name) for this plugin
	 * @param $product string
	 */
	function setProduct($product) {
		$this->setData('product', $product);
	}

	/**
	 * Get the category for this plugin
	 * @return string
	 */
	function getCategory() {
		return $this->getData('category');
	}

	/**
	 * Set the category for this plugin
	 * @param $category string
	 */
	function setCategory($category) {
		$this->setData('category', $category);
	}

	/**
	 * Get the newest compatible version of this plugin
	 * @return string
	 */
	function getVersion() {
		return $this->getData('version');
	}

	/**
	 * Set the version for this plugin
	 * @param $version string
	 */
	function setVersion($version) {
		$this->setData('version', $version);
	}

	/**
	 * Get the release date of this plugin
	 * @return int
	 */
	function getDate() {
		return $this->getData('date');
	}

	/**
	 * Set the release date for this plugin
	 * @param $date int
	 */
	function setDate($date) {
		$this->setData('date', $date);
	}

	/**
	 * Get the contact name for this plugin
	 * @return string
	 */
	function getContactName() {
		return $this->getData('contactName');
	}

	/**
	 * Set the contact name for this plugin
	 * @param $contactName string
	 */
	function setContactName($contactName) {
		$this->setData('contactName', $contactName);
	}

	/**
	 * Get the contact institution name for this plugin
	 * @return string
	 */
	function getContactInstitutionName() {
		return $this->getData('contactInstitutionName');
	}

	/**
	 * Set the contact institution name for this plugin
	 * @param $contactInstitutionName string
	 */
	function setContactInstitutionName($contactInstitutionName) {
		$this->setData('contactInstitutionName', $contactInstitutionName);
	}

	/**
	 * Get the contact email for this plugin
	 * @return string
	 */
	function getContactEmail() {
		return $this->getData('contactEmail');
	}

	/**
	 * Set the contact email for this plugin
	 * @param $contactEmail string
	 */
	function setContactEmail($contactEmail) {
		$this->setData('contactEmail', $contactEmail);
	}

	/**
	 * Get plugin summary.
	 * @param $locale string optional
	 * @return string
	 */
	function getSummary($locale = null) {
		return $this->getData('summary', $locale);
	}

	/**
	 * Set plugin summary.
	 * @param $summary string
	 * @param $locale string optional
	 */
	function setSummary($summary, $locale = null) {
		$this->setData('summary', $summary, $locale);
	}

	/**
	 * Get plugin description.
	 * @param $locale string optional
	 * @return string
	 */
	function getDescription($locale = null) {
		return $this->getData('description', $locale);
	}

	/**
	 * Set plugin description.
	 * @param $description string
	 * @param $locale string optional
	 */
	function setDescription($description, $locale = null) {
		$this->setData('description', $description, $locale);
	}

	/**
	 * Get plugin installation instructions.
	 * @param $locale string optional
	 * @return string
	 */
	function getInstallationInstructions($locale = null) {
		return $this->getData('installation', $locale);
	}

	/**
	 * Set plugin installation instructions.
	 * @param $installation string
	 * @param $locale string optional
	 */
	function setInstallationInstructions($installation, $locale = null) {
		$this->setData('installation', $installation, $locale);
	}

	/**
	 * Get release description.
	 * @param $locale string optional
	 * @return string
	 */
	function getReleaseDescription($locale = null) {
		return $this->getData('releaseDescription', $locale);
	}

	/**
	 * Set plugin release description.
	 * @param $releaseDescription string
	 * @param $locale string optional
	 */
	function setReleaseDescription($releaseDescription, $locale = null) {
		$this->setData('releaseDescription', $releaseDescription, $locale);
	}

	/**
	 * Get the certifications for this plugin release
	 * @return array
	 */
	function getReleaseCertifications() {
		return $this->getData('releaseCertifications');
	}

	/**
	 * Set the certifications for this plugin release
	 * @param $certifications array
	 */
	function setReleaseCertifications($certifications) {
		$this->setData('releaseCertifications', $certifications);
	}

	/**
	 * Get the package URL for this plugin release
	 * @return strings
	 */
	function getReleasePackage() {
		return $this->getData('releasePackage');
	}

	/**
	 * Set the package URL for this plugin release
	 * @param $package string
	 */
	function setReleasePackage($releasePackage) {
		$this->setData('releasePackage', $releasePackage);
	}

	/**
	 * Get the localized summary of the plugin.
	 * @return string
	 */
	function getLocalizedSummary() {
		return $this->getLocalizedData('summary');
	}

	/**
	 * Get the localized installation instructions of the plugin.
	 * @return string
	 */
	function getLocalizedInstallationInstructions() {
		return $this->getLocalizedData('installation');
	}

	/**
	 * Get the localized description of the plugin.
	 * @return string
	 */
	function getLocalizedDescription() {
		return $this->getLocalizedData('description');
	}

	/**
	 * Get the localized release description of the plugin.
	 * @return string
	 */
	function getLocalizedReleaseDescription() {
		return $this->getLocalizedData('releaseDescription');
	}

	/**
	 * Determine the version of this plugin that is currently installed,
	 * if any
	 */
	function getInstalledVersion() {
		$versionDao = DAORegistry::getDAO('VersionDAO'); /* @var $versionDao VersionDAO */
		return $versionDao->getCurrentVersion('plugins.' . $this->getCategory(), $this->getProduct(), true);
	}
}

?>
