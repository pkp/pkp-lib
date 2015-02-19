<?php

/**
 * @file classes/note/PKPNoteDAO.inc.php
 *
 * Copyright (c) 2013-2015 Simon Fraser University Library
 * Copyright (c) 2000-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NoteDAO
 * @ingroup note
 * @see Note
 *
 * @brief Operations for retrieving and modifying Note objects.
 */


class PKPNoteDAO extends DAO {
	/**
	 * Constructor.
	 */
	function PKPNoteDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve Note by note id
	 * @param $noteId int
	 * @return Note object
	 */
	function &getById($noteId) {
		$result =& $this->retrieve(
			'SELECT * FROM notes WHERE note_id = ?', (int) $noteId
		);

		$note =& $this->_returnNoteFromRow($result->GetRowAssoc(false));

		$result->Close();
		unset($result);

		return $note;
	}

	/**
	 * Retrieve Notes by user id
	 * @param $userId int
	 * @return object DAOResultFactory containing matching Note objects
	 */
	function &getByUserId($userId, $rangeInfo = null) {
		$result =& $this->retrieveRange(
			'SELECT * FROM notes WHERE user_id = ? ORDER BY date_created DESC',
		array((int) $userId), $rangeInfo
		);

		$returner = new DAOResultFactory($result, $this, '_returnNoteFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notes by assoc id/type
	 * @param $assocId int
	 * @param $assocType int
	 * @param $userId int
	 * @return object DAOResultFactory containing matching Note objects
	 */
	function &getByAssoc($assocType, $assocId, $userId = null) {
		$params = array((int) $assocId, (int) $assocType);
		if (isset($userId)) $params[] = (int) $userId;

		$sql = 'SELECT * FROM notes WHERE assoc_id = ? AND assoc_type = ?';
		if (isset($userId)) {
			$sql .= ' AND user_id = ?';
		}
		$sql .= ' ORDER BY date_created DESC';

		$result =& $this->retrieveRange($sql, $params);

		$returner = new DAOResultFactory($result, $this, '_returnNoteFromRow');

		return $returner;
	}

	/**
	 * Retrieve Notes by assoc id/type
	 * @param $assocId int
	 * @param $assocType int
	 * @param $userId int
	 * @return object DAOResultFactory containing matching Note objects
	 */
	function &notesExistByAssoc($assocType, $assocId, $userId = null) {
		$params = array((int) $assocId, (int) $assocType);
		if (isset($userId)) $params[] = (int) $userId;

		$sql = 'SELECT COUNT(*) FROM notes WHERE assoc_id = ? AND assoc_type = ?';
		if (isset($userId)) {
			$sql .= ' AND user_id = ?';
		}

		$result =& $this->retrieve($sql, $params);
		$returner = isset($result->fields[0]) && $result->fields[0] == 0 ? false : true;
		$result->Close();

		return $returner;
	}

	/**
	 * Creates and returns an note object from a row
	 * @param $row array
	 * @return Note object
	 */
	function &_returnNoteFromRow($row) {
		$note = $this->newDataObject();
		$note->setId($row['note_id']);
		$note->setUserId($row['user_id']);
		$note->setDateCreated($this->datetimeFromDB($row['date_created']));
		$note->setDateModified($this->datetimeFromDB($row['date_modified']));
		$note->setContents($row['contents']);
		$note->setTitle($row['title']);
		$note->setFileId($row['file_id']);
		$note->setAssocType($row['assoc_type']);
		$note->setAssocId($row['assoc_id']);

		HookRegistry::call('PKPNoteDAO::_returnNoteFromRow', array(&$note, &$row));

		return $note;
	}

	/**
	 * Inserts a new note into notes table
	 * @param Note object
	 * @return int Note Id
	 */
	function insertObject(&$note) {
		$this->update(
			sprintf('INSERT INTO notes
				(user_id, date_created, date_modified, title, contents, file_id, assoc_type, assoc_id)
				VALUES
				(?, %s, %s, ?, ?, ?, ?, ?)',
				$this->datetimeToDB(Core::getCurrentDate()),
				$this->datetimeToDB(Core::getCurrentDate())
			),
			array(
				(int) $note->getUserId(),
				$note->getTitle(),
				$note->getContents(),
				(int) $note->getFileId(),
				(int) $note->getAssocType(),
				(int) $note->getAssocId()
			)
		);

		$note->setId($this->getInsertNoteId());
		return $note->getId();
	}

	/**
	 * Update a note in the notes table
	 * @param Note object
	 * @return int Note Id
	 */
	function updateObject(&$note) {
		return $this->update(
			sprintf('UPDATE	notes SET
					user_id = ?,
					date_created = %s,
					date_modified = %s,
					title = ?,
					contents = ?,
					file_id = ?,
					assoc_type = ?,
					assoc_id = ?
				WHERE	note_id = ?',
				$this->datetimeToDB(Core::getCurrentDate()),
				$this->datetimeToDB(Core::getCurrentDate())
			),
			array(
				(int) $note->getUserId(),
				$note->getTitle(),
				$note->getContents(),
				(int) $note->getFileId(),
				(int) $note->getAssocType(),
				(int) $note->getAssocId(),
				(int) $note->getId()
			)
		);
	}

	/**
	 * Delete Note by note id
	 * @param $noteId int
	 * @return boolean
	 */
	function deleteById($noteId, $userId = null) {
		$params = array((int) $noteId);
		if (isset($userId)) $params[] = (int) $userId;

		return $this->update(
			'DELETE FROM notes WHERE note_id = ?' . (isset($userId) ? ' AND user_id = ?' : ''),
			$params
		);
	}

	/**
	 * Delete Note by note id
	 * @param $noteId int
	 * @return boolean
	 */
	function deleteByAssoc($assocType, $assocId) {
		return $this->update(
			'DELETE FROM notes WHERE assoc_type = ? AND assoc_id = ?',
			array((int) $assocType, (int) $assocId)
		);
	}

	/**
	 * get all note file ids by assoc
	 * @param $assocType int
	 * @param $assocId int
	 * @return array
	 */
	function getAllFileIds($assocType, $assocId) {
		$fileIds = array();

		$result =& $this->retrieve(
			'SELECT file_id FROM notes WHERE assoc_type = ? AND assoc_id = ? AND file_id <> 0', array((int) $assocType, (int) $assocId)
		);

		while (!$result->EOF) {
			$row = $result->GetRowAssoc(false);
			$fileIds[] = $row['file_id'];
			$result->MoveNext();
		}

		$result->Close();
		unset($result);

		return $fileIds;
	}

	/**
	 * Get the ID of the last inserted note
	 * @return int
	 */
	function getInsertNoteId() {
		return $this->getInsertId('notes', 'note_id');
	}
}

?>
