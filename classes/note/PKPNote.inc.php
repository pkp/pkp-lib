<?php

/**
 * @file classes/note/Note.inc.php
 *
 * Copyright (c) 2000-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Note
 * @ingroup note
 * @see NoteDAO
 * @brief Class for Note.
 */

// $Id$

import('lib.pkp.classes.note.NoteDAO');

class PKPNote extends DataObject {

	/**
	 * Constructor.
	 */
	function PKPNote() {
		parent::DataObject();
	}

	/**
	 * get user id of the note's author
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * set user id of the note's author
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}
	
	/**
	 * Return the user of the note's author.
	 * @return User
	 */
	function getUser() {
		$userDao =& DAORegistry::getDAO('UserDAO');
		return $userDao->getUser($this->getUserId(), true);
	}

	/**
	 * get date note was created
	 * @return date (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateCreated() {
		return $this->getData('dateCreated');
	}

	/**
	 * set date note was created
	 * @param $dateCreated date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateCreated($dateCreated) {
		return $this->setData('dateCreated', $dateCreated);
	}

	/**
	 * get note contents
	 * @return string
	 */
	function getContents() {
		return $this->getData('contents');
	}

	/**
	 * set note contents
	 * @param $contents int
	 */
	function setContents($contents) {
		return $this->setData('contents', $contents);
	}

	/**
	 * get note type
	 * @return int
	 */
	function getAssocType() {
		return $this->getData('assocType');
	}

	/**
	 * set note type
	 * @param $assocType int
	 */
	function setAssocType($assocType) {
		return $this->setData('assocType', $assocType);
	}

	/**
	 * get note assoc id
	 * @return int
	 */
	function getAssocId() {
		return $this->getData('assocId');
	}

	/**
	 * set note assoc id
	 * @param $assocId int
	 */
	function setAssocId($assocId) {
		return $this->setData('assocId', $assocId);
	}
	/**
	 * get context id
	 * @return int
	 */
	function getContext() {
		return $this->getData('context');
	}

	/**
	 * set context id
	 * @param $context int
	 */
	function setContext($context) {
		return $this->setData('context', $context);
	}

}

?>
