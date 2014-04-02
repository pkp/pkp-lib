<?php

/**
 * @file classes/note/PKPNote.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Note
 * @ingroup note
 * @see PKPNoteDAO
 * @brief Class for Note.
 */


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
		return $userDao->getById($this->getUserId(), true);
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
	 * get date note was modified
	 * @return date (YYYY-MM-DD HH:MM:SS)
	 */
	function getDateModified() {
		return $this->getData('dateModified');
	}

	/**
	 * set date note was modified
	 * @param $dateModified date (YYYY-MM-DD HH:MM:SS)
	 */
	function setDateModified($dateModified) {
		return $this->setData('dateModified', $dateModified);
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
	 * @param $contents string
	 */
	function setContents($contents) {
		return $this->setData('contents', $contents);
	}

	/**
	 * get note title
	 * @return string
	 */
	function getTitle() {
		return $this->getData('title');
	}

	/**
	 * set note title
	 * @param $title string
	 */
	function setTitle($title) {
		return $this->setData('title', $title);
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
	 * get file id
	 * @return int
	 */
	function getFileId() {
		return $this->getData('fileId');
	}

	/**
	 * set file id
	 * @param $fileId int
	 */
	function setFileId($fileId) {
		return $this->setData('fileId',$fileId);
	}
}

?>
