<?php

/**
 * @file classes/note/NoteDAO.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NoteDAO
 * @ingroup note
 * @see Note
 *
 * @brief Operations for retrieving and modifying Note objects.
 */

// $Id$

import('note.Note');

class NoteDAO extends DAO {
	/**
	 * Constructor.
	 */
	function NoteDAO() {
		parent::DAO();
	}

	/**
	 * Retrieve Note by note id
	 * @param $noteId int
	 * @return Note object
	 */
	function &getNoteById($noteId) {
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
	function &getNotesByUserId($userId, $level = NOTIFICATION_LEVEL_NORMAL, $rangeInfo = null) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		$result =& $this->retrieveRange(
			'SELECT * FROM notes WHERE user_id = ? AND context = ? ORDER BY date_created DESC',
			array((int) $userId, (int) $contextId), $rangeInfo
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
	function &getNotesByAssoc($assocId, $assocType, $userId = null) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		$params = array((int) $assocId, (int) $assocType, $contextId);
		if (isset($userId)) $params[] = (int) $userId;
		
		$sql = 'SELECT * FROM notes WHERE user_id = ? AND context = ?';
		if (isset($userId)) {
			$sql .= ' AND user_id = ?';
		}
		$sql .= ' ORDER BY date_created DESC';

		$result =& $this->retrieveRange($sql, $params);

		$returner = new DAOResultFactory($result, $this, '_returnNoteFromRow');

		return $returner;
	}

	/**
	 * Creates and returns an note object from a row
	 * @param $row array
	 * @return Note object
	 */
	function &_returnNoteFromRow($row) {
		$note = new Note();
		$note->setId($row['note_id']);
		$note->setUserId($row['user_id']);
		$note->setDateCreated($row['date_created']);
		$note->setContents($row['contents']);
		$note->setContext($row['context']);
		$note->setAssocType($row['assoc_type']);
		$note->setAssocId($row['assoc_id']);
		
		HookRegistry::call('NoteDAO::_returnNoteFromRow', array(&$note, &$row));

		return $note;
	}
	
	/**
	 * Inserts a new note into notes table
	 * @param Note object
	 * @return int Note Id
	 */
	function insertNote(&$note) {
		$application =& PKPApplication::getApplication();
		$productName = $application->getName();
	
		if ($this->noteAlreadyExists($note)) {
			return 0;
		}
	
		$this->update(
				sprintf('INSERT INTO notes
					(user_id, date_created, contents, context, assoc_type, assoc_id)
					VALUES
					(?, %s, ?, ?, ?, ?)',
					$this->datetimeToDB(date('Y-m-d H:i:s'))),
				array(
					(int) $note->getUserId(),
					$note->getTitle(),
					$note->getContents(),
					(int) $note->getContext(),
					(int) $note->getAssocType(),
					(int) $note->getAssocId()
					)
			);
	
			$note->setId($this->getInsertNoteId());
			return $note->getId();
	}

	/**
	 * Delete Note by note id
	 * @param $noteId int
	 * @return boolean
	 */
	function deleteNoteById($noteId, $userId = null) {
		$params = array($noteId);
		if (isset($userId)) $params[] = $userId;

		return $this->update('DELETE FROM notes WHERE note_id = ?' . (isset($userId) ? ' AND user_id = ?' : ''),
			$params
		);
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
