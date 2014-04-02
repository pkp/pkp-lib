<?php

/**
 * @file classes/security/UserGroup.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UserGroup
 * @ingroup security
 * @see UserGroupDAO
 *
 * @brief Describes user groups
 */

// Bring in role constants.
import('classes.security.Role');

class UserGroup extends DataObject {
	/**
	 * Constructor.
	 */
	function UserGroup() {
		parent::DataObject();
	}


	function getRoleId() {
		return $this->getData('roleId');
	}

	function setRoleId($roleId) {
		$this->setData('roleId', $roleId);
	}

	function getPath() {
		return $this->getData('path');
	}

	function setPath($path) {
		$this->setData('path', $path);
	}

	function getContextId() {
		return $this->getData('contextId');
	}

	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
	}


	function getDefault() {
		return $this->getData('isDefault');
	}

	function setDefault($isDefault) {
		$this->setData('isDefault', $isDefault);
	}

	function getLocalizedName() {
		return $this->getLocalizedData('name');
	}

	/**
	 * Get user group name
	 * @param $locale string
	 * @return string
	 */
	function getName($locale) {
		return $this->getData('name', $locale);
	}

	/**
	 * Set user group name
	 * @param $name string
	 * @param $locale string
	 */
	function setName($name, $locale) {
		return $this->setData('name', $name, $locale);
	}

	function getLocalizedAbbrev() {
		return $this->getLocalizedData('abbrev');
	}

	/**
	 * Get user group abbrev
	 * @param $locale string
	 * @return string
	 */
	function getAbbrev($locale) {
		return $this->getData('abbrev', $locale);
	}

	/**
	 * Set user group abbrev
	 * @param $abbrev string
	 * @param $locale string
	 */
	function setAbbrev($abbrev, $locale) {
		return $this->setData('abbrev', $abbrev, $locale);
	}
}


?>
