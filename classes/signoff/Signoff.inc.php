<?php

/**
 * @defgroup signoff Signoff
 * Implements signoffs, i.e. opportunities to respond to an assigned item with approval.
 */

/**
 * @file classes/signoff/Signoff.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
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
	 * Get assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * Set assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		$this->setData('assocId', $assocId);
	}

	/**
	 * Get associated type.
	 * @return int ASSOC_TYPE_...
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * Set associated type.
	 * @param $assocType int ASSOC_TYPE_...
	 */
	function setAssocType($assocType) {
		$this->setData('assocType', $assocType);
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
		$this->setData('symbolic', $symbolic);
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
		$this->setData('userId', $userId);
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
		$this->setData('fileId', $fileId);
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
		$this->setData('fileRevision', $fileRevision);
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
		$this->setData('dateNotified', $dateNotified);
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
		$this->setData('dateUnderway', $dateUnderway);
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
		$this->setData('dateCompleted', $dateCompleted);
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
		$this->setData('dateAcknowledged', $dateAcknowledged);
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
		$this->setData('userGroupId', $userGroupId);
	}
}

?>
