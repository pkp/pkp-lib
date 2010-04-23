<?php

/**
 * @file classes/note/NoteManager.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class NoteManager
 * @ingroup note
 * @see NoteDAO
 * @see Note
 * @brief Class for Note Manager.
 */

// $Id$

import('note.Note');

class NoteManager {
	/**
	 * Constructor.
	 */
	function NoteManager() {
	}

	/**
	 * Create a new note with the specified arguments and insert into DB
	 * @param $userId int
	 * @param $contents string
	 * @param $assocType int
	 * @param $assocId int
	 * @return Note object
	 */
	function createNote($userId, $contents, $assocType, $assocId) {
		$note = new Note();
		$context =& Request::getContext();
		$contextId = $context?$context->getId():0;

		$note->setUserId($userId);
		$note->setContents($contents);
		$note->setAssocType($assocType);
		$note->setAssocId($assocType);
		$note->setContext($contextId);

		$noteDao =& DAORegistry::getDAO('NoteDAO');
		$noteDao->insertNote($note);

		return $note;
	}

}

?>
