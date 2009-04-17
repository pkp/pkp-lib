<?php

/**
 * @file Role.inc.php
 *
 * Copyright (c) 2000-2008 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Role
 * @ingroup security
 * @see RoleDAO
 *
 * @brief Describes user roles within the system and the associated permissions.
 */

//$Id$


class PKPRole extends DataObject {

	/**
	 * Constructor.
	 */
	function PKPRole() {
		parent::DataObject();
	}

	/**
	 * Get the i18n key name associated with this role.
	 * @return String the key
	 */
	function getRoleName() {
		return RoleDAO::getRoleName($this->getData('roleId'));
	}

	/**
	 * Get the URL path associated with this role's operations.
	 * @return String the path
	 */
	function getRolePath() {
		return RoleDAO::getRolePath($this->getData('roleId'));
	}

	//
	// Get/set methods
	//

	/**
	 * Get assoc ID for this role.
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set assoc ID for this role.
	 * @param $journalId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * Get assoc type for this role.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set assoc type for this role.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}
	
	/**
	 * Get user ID associated with role.
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * Set user ID associated with role.
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}

	/**
	 * Get role ID of this role.
	 * @return int
	 */
	function getRoleId() {
		return $this->getData('roleId');
	}

	/**
	 * Set role ID of this role.
	 * @param $roleId int
	 */
	function setRoleId($roleId) {
		return $this->setData('roleId', $roleId);
	}
}

?>
