<?php

/**
 * @file classes/site/Version.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Version
 * @ingroup site
 * @see VersionDAO
 *
 * @brief Describes system version history.
 */

// $Id$


class Version extends DataObject {

	/**
	 * Constructor.
	 */
	function Version() {
		parent::DataObject();
	}

	/**
	 * Compare this version with another version.
	 * Returns:
	 *     < 0 if this version is lower
	 *     0 if they are equal
	 *     > 0 if this version is higher
	 * @param $version string/Version the version to compare against
	 * @return int
	 */
	function compare($version) {
		if (is_object($version)) {
			return $this->compare($version->getVersionString());
		}
		return version_compare($this->getVersionString(), $version);
	}

	/**
	 * Static method to return a new version from a version string of the form "W.X.Y.Z".
	 * @param $versionString string
	 * @param $product string
	 * @param $productType string
	 * @return Version
	 */
	function &fromString($versionString, $product = null, $productType = null) {
		$version = new Version();

		if(!$product && !$productType) {
			$application = PKPApplication::getApplication();
			$product = $application->getName();
			$productType = 'core';
		}

		$versionArray = explode('.', $versionString);
		$version->setMajor(isset($versionArray[0]) ? (int) $versionArray[0] : 0);
		$version->setMinor(isset($versionArray[1]) ? (int) $versionArray[1] : 0);
		$version->setRevision(isset($versionArray[2]) ? (int) $versionArray[2] : 0);
		$version->setBuild(isset($versionArray[3]) ? (int) $versionArray[3] : 0);
		$version->setDateInstalled(null);
		$version->setProduct($product);
		$version->setProductType($productType);

		return $version;
	}

	//
	// Get/set methods
	//

	/**
	 * Get major version.
	 * @return int
	 */
	function getMajor() {
		return $this->getData('major');
	}

	/**
	 * Set major version.
	 * @param $major int
	 */
	function setMajor($major) {
		return $this->setData('major', $major);
	}

	/**
	 * Get minor version.
	 * @return int
	 */
	function getMinor() {
		return $this->getData('minor');
	}

	/**
	 * Set minor version.
	 * @param $minor int
	 */
	function setMinor($minor) {
		return $this->setData('minor', $minor);
	}

	/**
	 * Get revision version.
	 * @return int
	 */
	function getRevision() {
		return $this->getData('revision');
	}

	/**
	 * Set revision version.
	 * @param $revision int
	 */
	function setRevision($revision) {
		return $this->setData('revision', $revision);
	}

	/**
	 * Get build version.
	 * @return int
	 */
	function getBuild() {
		return $this->getData('build');
	}

	/**
	 * Set build version.
	 * @param $build int
	 */
	function setBuild($build) {
		return $this->setData('build', $build);
	}

	/**
	 * Get date installed.
	 * @return date
	 */
	function getDateInstalled() {
		return $this->getData('dateInstalled');
	}

	/**
	 * Set date installed.
	 * @param $dateInstalled date
	 */
	function setDateInstalled($dateInstalled) {
		return $this->setData('dateInstalled', $dateInstalled);
	}

	/**
	 * Check if current version.
	 * @return int
	 */
	function getCurrent() {
		return $this->getData('current');
	}

	/**
	 * Set if current version.
	 * @param $current int
	 */
	function setCurrent($current) {
		return $this->setData('current', $current);
	}

	/**
	 * Get product name.
	 * @return string
	 */
	function getProduct() {
		return $this->getData('product');
	}

	/**
	 * Set product name.
	 * @param $product string
	 */
	function setProduct($product) {
		return $this->setData('product', $product);
	}

	/**
	 * Get product type.
	 * @return string
	 */
	function getProductType() {
		return $this->getData('productType');
	}

	/**
	 * Set product type.
	 * @param $product string
	 */
	function setProductType($productType) {
		return $this->setData('productType', $productType);
	}

	/**
	 * Return complete version string.
	 * @return string
	 */
	function getVersionString() {
		return sprintf('%d.%d.%d.%d', $this->getMajor(), $this->getMinor(), $this->getRevision(), $this->getBuild());
	}
}

?>
