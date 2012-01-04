<?php

/**
 * @file SignoffDAO.inc.php
 *
 * Copyright (c) 2000-2012 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffDAO
 * @ingroup signoff
 * @see Signoff
 *
 * @brief Operations for retrieving and modifying Signoff objects.
 */

//$Id$


import('signoff.Signoff');

class SignoffDAO extends DAO {
	/**
	 * Retrieve a signoff by ID.
	 * @param $signoffId int
	 * @return Signoff
	 */
	function getById($signoffId) {
		$result =& $this->retrieve(
			'SELECT * FROM signoffs WHERE signoff_id = ?', array((int) $signoffId)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner =& $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Fetch a signoff by symbolic info, building it if needed.
	 * @param $symbolic string
	 * @param $assocType int
	 * @param $assocId int
	 * @return $signoff
	 */
	function build($symbolic, $assocType, $assocId) {
		// If one exists, fetch and return.
		$signoff = $this->getBySymbolic($symbolic, $assocType, $assocId);
		if ($signoff) return $signoff;

		// Otherwise, build one.
		unset($signoff);
		$signoff = $this->newDataObject();
		$signoff->setSymbolic($symbolic);
		$signoff->setAssocType($assocType);
		$signoff->setAssocId($assocId);
		$this->insertObject($signoff);
		return $signoff;
	}

	/**
	 * Construct a new data object corresponding to this DAO.
	 * @return SignoffEntry
	 */
	function newDataObject() {
		return new Signoff();
	}

	/**
	 * Internal function to return an Signoff object from a row.
	 * @param $row array
	 * @return Signoff
	 */
	function _fromRow(&$row) {
		$signoff = $this->newDataObject();

		$signoff->setId($row['signoff_id']);
		$signoff->setAssocType($row['assoc_type']);
		$signoff->setAssocId($row['assoc_id']);
		$signoff->setSymbolic($row['symbolic']);
		$signoff->setUserId($row['user_id']);
		$signoff->setFileId($row['file_id']);
		$signoff->setFileRevision($row['file_revision']);
		$signoff->setDateNotified($this->datetimeFromDB($row['date_notified']));
		$signoff->setDateUnderway($this->datetimeFromDB($row['date_underway']));
		$signoff->setDateCompleted($this->datetimeFromDB($row['date_completed']));
		$signoff->setDateAcknowledged($this->datetimeFromDB($row['date_acknowledged']));

		return $signoff;
	}

	/**
	 * Insert a new Signoff.
	 * @param $signoff Signoff
	 * @return int
	 */
	function insertObject(&$signoff) {
		$this->update(
			sprintf(
				'INSERT INTO signoffs
				(symbolic, assoc_type, assoc_id, user_id, file_id, file_revision, date_notified, date_underway, date_completed, date_acknowledged)
				VALUES
				(?, ?, ?, ?, ?, ?, %s, %s, %s, %s)',
				$this->datetimeToDB($signoff->getDateNotified()),
				$this->datetimeToDB($signoff->getDateUnderway()),
				$this->datetimeToDB($signoff->getDateCompleted()),
				$this->datetimeToDB($signoff->getDateAcknowledged())
			),
			array(
				$signoff->getSymbolic(),
				(int) $signoff->getAssocType(),
				(int) $signoff->getAssocId(),
				(int) $signoff->getUserId(),
				$this->nullOrInt($signoff->getFileId()),
				$this->nullOrInt($signoff->getFileRevision())
			)
		);
		$signoff->setId($this->getInsertId());
		return $signoff->getId();
	}

	/**
	 * Update an existing signoff.
	 * @param $signoff Signoff
	 * @return boolean
	 */
	function updateObject(&$signoff) {
		$returner = $this->update(
			sprintf(
				'UPDATE	signoffs
				SET	symbolic = ?,
					assoc_type = ?,
					assoc_id = ?,
					user_id = ?,
					file_id = ?,
					file_revision = ?,
					date_notified = %s,
					date_underway = %s,
					date_completed = %s,
					date_acknowledged = %s
				WHERE	signoff_id = ?',
				$this->datetimeToDB($signoff->getDateNotified()),
				$this->datetimeToDB($signoff->getDateUnderway()),
				$this->datetimeToDB($signoff->getDateCompleted()),
				$this->datetimeToDB($signoff->getDateAcknowledged())
			),
			array(
				$signoff->getSymbolic(),
				(int) $signoff->getAssocType(),
				(int) $signoff->getAssocId(),
				(int) $signoff->getUserId(),
				$this->nullOrInt($signoff->getFileId()),
				$this->nullOrInt($signoff->getFileRevision()),
				(int) $signoff->getId()
			)
		);
		return $returner;
	}

	/**
	 * Delete a signoff.
	 * @param $signoff Signoff
	 * @return boolean
	 */
	function deleteObject($signoff) {
		return $this->deleteObjectById($signoff->getId());
	}

	/**
	 * Delete a signoff by ID.
	 * @param $signoffId int
	 * @return boolean
	 */
	function deleteObjectById($signoffId) {
		return $this->update('DELETE FROM signoffs WHERE signoff_id = ?', array((int) $signoffId));
	}

	/**
	 * Retrieve an array of signoffs matching the specified
	 * symbolic name and assoc info.
	 * @param $symbolic string
	 * @param $assocType int
	 * @param $assocId int
	 */
	function getBySymbolic($symbolic, $assocType, $assocId) {
		$result =& $this->retrieve(
			'SELECT * FROM signoffs WHERE symbolic = ? AND assoc_type = ? AND assoc_id = ?',
			array($symbolic, (int) $assocType, (int) $assocId)
		);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get the ID of the last inserted signoff.
	 * @return int
	 */
	function getInsertId() {
		return parent::getInsertId('signoffs', 'signoff_id');
	}
}

?>
