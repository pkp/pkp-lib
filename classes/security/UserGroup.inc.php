<?php

/**
 * @file classes/security/UserGroup.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserGroup
 * @ingroup security
 * @see UserGroupDAO
 *
 * @brief Describes user groups
 */

// Bring in role constants.
import('lib.pkp.classes.security.Role');

class UserGroup extends DataObject {

	/**
	 * Get the role ID
	 * @return int ROLE_ID_...
	 */
	function getRoleId() {
		return $this->getData('roleId');
	}

	/**
	 * Set the role ID
	 * @param $roleId int ROLE_ID_...
	 */
	function setRoleId($roleId) {
		$this->setData('roleId', $roleId);
	}

	/**
	 * Get the role path
	 * @return string Role path
	 */
	function getPath() {
		return $this->getData('path');
	}

	/**
	 * Set the role path
	 * $param $path string
	 */
	function setPath($path) {
		$this->setData('path', $path);
	}

	/**
	 * Get the context ID
	 * @return int
	 */
	function getContextId() {
		return $this->getData('contextId');
	}

	/**
	 * Set the context ID
	 * @param $contextId int
	 */
	function setContextId($contextId) {
		$this->setData('contextId', $contextId);
	}

	/**
	 * Get the default flag
	 * @return boolean
	 */
	function getDefault() {
		return $this->getData('isDefault');
	}

	/**
	 * Set the default flag
	 * @param $isDefault boolean
	 */
	function setDefault($isDefault) {
		$this->setData('isDefault', $isDefault);
	}

	/**
	 * Get the "show title" flag (whether or not the title of the role
	 * should be included in the list of submission contributor names)
	 * @return boolean
	 */
	function getShowTitle() {
		return $this->getData('showTitle');
	}

	/**
	 * Set the "show title" flag
	 * @param $showTitle boolean
	 */
	function setShowTitle($showTitle) {
		$this->setData('showTitle', $showTitle);
	}

	/**
	 * Get the "permit self-registration" flag (whether or not users may
	 * self-register for this role, i.e. in the case of external
	 * reviewers, or whether it should be prohibited, in the case of
	 * internal reviewers).
	 * @return boolean True IFF user self-registration is permitted
	 */
	function getPermitSelfRegistration() {
		return $this->getData('permitSelfRegistration');
	}

	/**
	 * Set the "permit self-registration" flag
	 * @param $permitSelfRegistration boolean
	 */
	function setPermitSelfRegistration($permitSelfRegistration) {
		$this->setData('permitSelfRegistration', $permitSelfRegistration);
	}

	/**
	 * Get the recommendOnly option (whether or not the manager or the sub-editor role
	 * can only recommend or also make decisions in the submission review)
	 * @return boolean
	 */
	function getRecommendOnly() {
		return $this->getData('recommendOnly');
	}

	/**
	 * Set the recommendOnly option (whether or not the manager or the sub-editor role
	 * can only recommend or also make decisions in the submission review)
	 * @param $recommendOnly boolean
	 */
	function setRecommendOnly($recommendOnly) {
		$this->setData('recommendOnly', $recommendOnly);
	}

	/**
	 * Get the localized role name
	 * @return string
	 */
	function getLocalizedName() {
		return $this->getLocalizedData('name');
	}

	/**
	 * Get localized user group name, or array of localized names if $locale is null
	 * @param $locale string|null
	 * @return string|array|null localized name or array of localized names or null
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
		$this->setData('name', $name, $locale);
	}

	/**
	 * Get the localized abbreviation
	 * @return string
	 */
	function getLocalizedAbbrev() {
		return $this->getLocalizedData('abbrev');
	}

	/**
	 * Get localized user group abbreviation, or array of localized abbreviations if $locale is null
	 * @param $locale string|null
	 * @return string|array|null localized abbreviation or array of localized abbreviations or null
	 */
	function getAbbrev($locale) {
		return $this->getData('abbrev', $locale);
	}

	/**
	 * Set user group abbreviation
	 * @param $abbrev string
	 * @param $locale string
	 */
	function setAbbrev($abbrev, $locale) {
		$this->setData('abbrev', $abbrev, $locale);
	}

	/**
	 * Getter for permitMetadataEdit attribute.
	 * @return boolean
	 */
	function getPermitMetadataEdit() {
		return $this->getData('permitMetadataEdit');
	}

	/**
	 * Setter for permitMetadataEdit attribute.
	 * @param $permitMetadataEdit boolean
	 */
	function setPermitMetadataEdit($permitMetadataEdit) {
		$this->setData('permitMetadataEdit', $permitMetadataEdit);
	}
}



