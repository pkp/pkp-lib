<?php

/**
 * @file SignoffDAO.inc.php
 *
 * Copyright (c) 2000-2011 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffDAO
 * @ingroup signoff
 * @see Signoff
 *
 * @brief Operations for retrieving and modifying Signoff objects.
 */


import('lib.pkp.classes.signoff.Signoff');

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
	function build($symbolic, $assocType, $assocId, $userId = null, $stageId = null, $userGroupId = null) {
		// If one exists, fetch and return.
		$signoff = $this->getBySymbolic($symbolic, $assocType, $assocId, $userId, $stageId, $userGroupId);
		if ($signoff) return $signoff;

		// Otherwise, build one.
		unset($signoff);
		$signoff = $this->newDataObject();
		$signoff->setSymbolic($symbolic);
		$signoff->setAssocType($assocType);
		$signoff->setAssocId($assocId);
		$signoff->setUserId($userId);
		$signoff->setStageId($stageId);
		$signoff->setUserGroupId($userGroupId);
		$this->insertObject($signoff);
		return $signoff;
	}

	/**
	 * Determine if a signoff exists
	 * @param string $symbolic
	 * @param int $assocType
	 * @param int $assocId
	 * @param int $stageId
	 * @param int $userGroupId
	 * @return boolean
	 */
	function signoffExists($symbolic, $assocType, $assocId, $userId = null, $stageId = null, $userGroupId = null) {
		$sql = 'SELECT COUNT(*) FROM signoffs WHERE symbolic = ? AND assoc_type = ? AND assoc_id = ?';
		$params = array($symbolic, (int) $assocType, (int) $assocId);

		if ($userId) {
			$sql .= ' AND user_id = ?';
			$params[] = (int) $userId;
		}

		if ($stageId) {
			$sql .= ' AND stage_id = ?';
			$params[] = (int) $stageId;
		}

		if ($userGroupId) {
			$sql .= ' AND user_group_id = ?';
			$params[] = (int) $userGroupId;
		}

		$result =& $this->retrieve($sql, $params);

		$returner = isset($result->fields[0]) && $result->fields[0] > 0 ? true : false;

		$result->Close();
		unset($result);

		return $returner;
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
		$signoff->setStageId($row['stage_id']);
		$signoff->setUserGroupId($row['user_group_id']);

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
				(symbolic, assoc_type, assoc_id, user_id, file_id, file_revision, date_notified, date_underway, date_completed, date_acknowledged, stage_id, user_group_id)
				VALUES
				(?, ?, ?, ?, ?, ?, %s, %s, %s, %s, ?, ?)',
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
				$this->nullOrInt($signoff->getStageId()),
				$this->nullOrInt($signoff->getUserGroupId())
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
					date_acknowledged = %s,
					stage_id = ?,
					user_group_id = ?
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
				$this->nullOrInt($signoff->getStageId()),
				$this->nullOrInt($signoff->getUserGroupId()),
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
	 * Retrieve the first signoff matching the specified symbolic name and assoc info.
	 * @param $symbolic string
	 * @param $assocType int
	 * @param $assocId int
	 */
	function getBySymbolic($symbolic, $assocType, $assocId, $userId = null, $stageId = null, $userGroupId = null) {
		$sql = 'SELECT * FROM signoffs WHERE symbolic = ? AND assoc_type = ? AND assoc_id = ?';
		$params = array($symbolic, (int) $assocType, (int) $assocId);

		if ($userId) {
			$sql .= ' AND user_id = ?';
			$params[] = (int) $userId;
		}

		if ($stageId) {
			$sql .= ' AND stage_id = ?';
			$params[] = (int) $stageId;
		}

		if ($userGroupId) {
			$sql .= ' AND user_group_id = ?';
			$params[] = (int) $userGroupId;
		}

		$result =& $this->retrieve($sql, $params);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Retrieve all signoffs matching the specified input parameters
	 * @param $symbolic string
	 * @param $assocType int
	 * @param $assocId int
	 * @return object
	 */
	function getAllBySymbolic($symbolic, $assocType, $assocId, $userId = null, $stageId = null, $userGroupId = null) {
		$sql = 'SELECT * FROM signoffs WHERE symbolic = ? AND assoc_type = ? AND assoc_id = ?';
		$params = array($symbolic, (int) $assocType, (int) $assocId);

		if ($userId) {
			$sql .= ' AND user_id = ?';
			$params[] = (int) $userId;
		}

		if ($stageId) {
			$sql .= ' AND stage_id = ?';
			$params[] = (int) $stageId;
		}

		if ($userGroupId) {
			$sql .= ' AND user_group_id = ?';
			$params[] = (int) $userGroupId;
		}

		$result =& $this->retrieve($sql, $params);

		$returner = new DAOResultFactory($result, $this, '_fromRow', array('id'));
		return $returner;
	}

	/**
	 * Retrieve an array of signoffs matching the specified user id
	 * @param $userId int
	 */
	function getByUserId($userId) {
		$sql = 'SELECT * FROM signoffs WHERE user_id = ?';
		$params = array((int) $userId);

		$result =& $this->retrieve($sql, $params);

		$returner = null;
		if ($result->RecordCount() != 0) {
			$returner = $this->_fromRow($result->GetRowAssoc(false));
		}
		$result->Close();
		return $returner;
	}

	/**
	 * Get all users assigned to a particular workflow stage
	 * @param $monographId int
	 * @param $stageId int optional
	 * @param $userGroupId int optional
 	 */
	function &getUsersBySymbolic($symbolic, $assocType, $assocId, $stageId = null, $userGroupId = null, $unique = true) {
		$selectDistinct = $unique ? 'SELECT DISTINCT' : 'SELECT';
		$sql = $selectDistinct . ' u.* FROM users u, signoffs s
				WHERE u.user_id = s.user_id AND s.symbolic = ? AND s.assoc_type = ? AND s.assoc_id = ?';
		$params = array($symbolic, (int) $assocType, (int) $assocId);

		if ($stageId) {
			$sql .= ' AND s.stage_id = ?';
			$params[] = (int) $stageId;
		}

		if ($userGroupId) {
			$sql .= ' AND s.user_group_id = ?';
			$params[] = (int) $userGroupId;
		}

		$result =& $this->retrieve($sql, $params);

		$userDao =& DAORegistry::getDAO('UserDAO');
		$returner = new DAOResultFactory($result, $userDao, '_returnUserFromRow');
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
