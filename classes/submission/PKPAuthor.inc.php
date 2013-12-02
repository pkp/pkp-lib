<?php

/**
 * @file classes/submission/PKPAuthor.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPAuthor
 * @ingroup submission
 * @see PKPAuthorDAO
 *
 * @brief Author metadata class.
 */

import('lib.pkp.classes.identity.Identity');

class PKPAuthor extends Identity {
	/**
	 * Constructor.
	 */
	function PKPAuthor() {
		parent::Identity();
	}

	//
	// Get/set methods
	//

	/**
	 * Get ID of submission.
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * Set ID of submission.
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		return $this->setData('submissionId', $submissionId);
	}

	/**
	 * Set the user group id
	 * @param $userGroupId int
	 */
	function setUserGroupId($userGroupId) {
		$this->setData('userGroupId', $userGroupId);
	}

	/**
	 * Get the user group id
	 * @return int
	 */
	function getUserGroupId() {
		return $this->getData('userGroupId');
	}

	/**
	 * Get primary contact.
	 * @return boolean
	 */
	function getPrimaryContact() {
		return $this->getData('primaryContact');
	}

	/**
	 * Set primary contact.
	 * @param $primaryContact boolean
	 */
	function setPrimaryContact($primaryContact) {
		return $this->setData('primaryContact', $primaryContact);
	}

	/**
	 * Get sequence of author in submissions' author list.
	 * @return float
	 */
	function getSequence() {
		return $this->getData('sequence');
	}

	/**
	 * Set sequence of author in submissions' author list.
	 * @param $sequence float
	 */
	function setSequence($sequence) {
		return $this->setData('sequence', $sequence);
	}

	/**
	 * Get the user group for this contributor.
	 */
	function getUserGroup() {
		//FIXME: should this be queried when fetching Author from DB? - see #5231.
		static $userGroup; // Frequently we'll fetch the same one repeatedly
		if (!$userGroup || $this->getUserGroupId() != $userGroup->getId()) {
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
			$userGroup = $userGroupDao->getById($this->getUserGroupId());
		}
		return $userGroup;
	}

	/**
	 * Get a localized version of the User Group
	 * @return string
	 */
	function getLocalizedUserGroupName() {
		$userGroup = $this->getUserGroup();
		return $userGroup->getLocalizedName();
	}
}

?>
