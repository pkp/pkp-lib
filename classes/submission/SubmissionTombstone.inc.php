<?php

/**
 * @file classes/submission/SubmissionTombstone.inc.php
 *
 * Copyright (c) 2003-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionTombstone
 * @ingroup submission
 *
 * @brief Base class for submission tombstones.
 */

class SubmissionTombstone extends DataObject {
	/**
	 * Constructor.
	 */
	function SubmissionTombstone() {
		parent::DataObject();
	}

	/**
	 * get submission id
	 * @return int
	 */
	function getSubmissionId() {
		return $this->getData('submissionId');
	}

	/**
	 * set submission id
	 * @param $submissionId int
	 */
	function setSubmissionId($submissionId) {
		return $this->setData('submissionId', $submissionId);
	}

	/**
	 * get date deleted
	 * @return date
	 */
	function getDateDeleted() {
		return $this->getData('dateDeleted');
	}

	/**
	 * set date deleted
	 * @param $dateDeleted date
	 */
	function setDateDeleted($dateDeleted) {
		return $this->setData('dateDeleted', $dateDeleted);
	}

	/**
	 * Stamp the date of the deletion to the current time.
	 */
	function stampDateDeleted() {
		return $this->setDateDeleted(Core::getCurrentDate());
	}

	/**
	 * Get oai setSpec.
	 * @return string
	 */
	function getSetSpec() {
		return $this->getData('setSpec');
	}

	/**
	 * Set oai setSpec.
	 * @param $setSpec string
	 */
	function setSetSpec($setSpec) {
		return $this->setData('setSpec', $setSpec);
	}

	/**
	 * Get oai setName.
	 * @return string
	 */
	function getSetName() {
		return $this->getData('setName');
	}

	/**
	 * Set oai setName.
	 * @param $setName string
	 */
	function setSetName($setName) {
		return $this->setData('setName', $setName);
	}

	/**
	 * Get oai identifier.
	 * @return string
	 */
	function getOAIIdentifier() {
		return $this->getData('oaiIdentifier');
	}

	/**
	 * Set oai identifier.
	 * @param $oaiIdentifier string
	 */
	function setOAIIdentifier($oaiIdentifier) {
		return $this->setData('oaiIdentifier', $oaiIdentifier);
	}
}

?>