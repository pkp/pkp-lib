<?php

/**
 * @defgroup signoff
 */

/**
 * @file classes/signoff/Signoff.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Signoff
 * @ingroup signoff
 * @see SignoffDAO
 *
 * @brief Basic class describing a signoff.
 */


class Signoff extends DataObject {
	/**
	 * Constructor
	 */
	function Signoff() {
		parent::DataObject();
	}

	//
	// Get/set methods
	//

	/**
	 * get assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * set assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}

	/**
	 * Get associated type.
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set associated type.
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * Get symbolic name.
	 * @return string
	 */
	function getSymbolic() {
		return $this->getData('symbolic');
	}

	/**
	 * Set symbolic name.
	 * @param $symbolic string
	 */
	function setSymbolic($symbolic) {
		return $this->setData('symbolic', $symbolic);
	}

	/**
	 * Get user ID for this signoff.
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * Set user ID for this signoff.
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}

	/**
	 * Get file ID for this signoff.
	 * @return int
	 */
	function getFileId() {
		return $this->getData('fileId');
	}

	/**
	 * Set file ID for this signoff.
	 * @param $fileId int
	 */
	function setFileId($fileId) {
		return $this->setData('fileId', $fileId);
	}

	/**
	 * Get file revision for this signoff.
	 * @return int
	 */
	function getFileRevision() {
		return $this->getData('fileRevision');
	}

	/**
	 * Set file revision for this signoff.
	 * @param $fileRevision int
	 */
	function setFileRevision($fileRevision) {
		return $this->setData('fileRevision', $fileRevision);
	}

	/**
	 * Get date notified.
	 * @return string
	 */
	function getDateNotified() {
		return $this->getData('dateNotified');
	}

	/**
	 * Set date notified.
	 * @param $dateNotified string
	 */
	function setDateNotified($dateNotified) {
		return $this->setData('dateNotified', $dateNotified);
	}

	/**
	 * Get date underway.
	 * @return string
	 */
	function getDateUnderway() {
		return $this->getData('dateUnderway');
	}

	/**
	 * Set date underway.
	 * @param $dateUnderway string
	 */
	function setDateUnderway($dateUnderway) {
		return $this->setData('dateUnderway', $dateUnderway);
	}

	/**
	 * Get date completed.
	 * @return string
	 */
	function getDateCompleted() {
		return $this->getData('dateCompleted');
	}

	/**
	 * Set date completed.
	 * @param $dateCompleted string
	 */
	function setDateCompleted($dateCompleted) {
		return $this->setData('dateCompleted', $dateCompleted);
	}

	/**
	 * Get date acknowledged.
	 * @return string
	 */
	function getDateAcknowledged() {
		return $this->getData('dateAcknowledged');
	}

	/**
	 * Set date acknowledged.
	 * @param $dateAcknowledged string
	 */
	function setDateAcknowledged($dateAcknowledged) {
		return $this->setData('dateAcknowledged', $dateAcknowledged);
	}

	/**
	 * Get id of user group the user is acting as.
	 * @return string
	 */
	function getUserGroupId() {
		return $this->getData('userGroupId');
	}

	/**
	 * Set id of user group the user is acting as.
	 * @param $userGroupId string
	 */
	function setUserGroupId($userGroupId) {
		return $this->setData('userGroupId', $userGroupId);
	}
}

?>
