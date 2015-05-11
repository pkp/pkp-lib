<?php

/**
 * @file classes/submission/SubmissionFileQuery.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileQuery
 * @ingroup submission
 * @see SubmissionFileQueryDAO
 *
 * @brief Class for SubmissionFileQuery.
 */


class SubmissionFileQuery extends DataObject {
	/**
	 * Constructor.
	 */
	function SubmissionFileQuery() {
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
	 * get parent query id
	 * @return int
	 */
	function getParentQueryId() {
		return $this->getData('parentQueryId');
	}

	/**
	 * set parent query id
	 * @param $parentQueryId int
	 */
	function setParentQueryId($parentQueryId) {
		return $this->setData('parentQueryId', $parentQueryId);
	}

	/**
	 * get stage id
	 * @return int
	 */
	function getStageId() {
		return $this->getData('stageId');
	}

	/**
	 * set stage id
	 * @param $stageId int
	 */
	function setStageId($stageId) {
		return $this->setData('stageId', $stageId);
	}

	/**
	 * get user id
	 * @return int
	 */
	function getUserId() {
		return $this->getData('userId');
	}

	/**
	 * set user id
	 * @param $userId int
	 */
	function setUserId($userId) {
		return $this->setData('userId', $userId);
	}

	/**
	 * get thread closed
	 * @return int
	 */
	function getThreadClosed() {
		return $this->getData('threadClosed');
	}

	/**
	 * set thread closed
	 * @param $threadClosed int
	 */
	function setThreadClosed($threadClosed) {
		return $this->setData('threadClosed', $threadClosed);
	}

	/**
	 * get user
	 * @return PKPUser
	 */
	function getUser() {
		$userDao = DAORegistry::getDAO('UserDAO');
		return $userDao->getById($this->getUserId());
	}

	/**
	 * get date posted
	 * @return date
	 */
	function getDatePosted() {
		return $this->getData('datePosted');
	}

	/**
	 * Returns the date posted in short display form.
	 * @return string
	 */
	function getShortDatePosted() {
		return date('M/d', strtotime($this->getDatePosted()));
	}

	/**
	 * set date posted
	 * @param $datePosted date
	 */
	function setDatePosted($datePosted) {
		return $this->setData('datePosted', $datePosted);
	}

	/**
	 * get date modified
	 * @return date
	 */
	function getDateModified() {
		return $this->getData('dateModified');
	}

	/**
	 * set date modified
	 * @param $dateModified date
	 */
	function setDateModified($dateModified) {
		return $this->setData('dateModified', $dateModified);
	}

	/**
	 * get viewable
	 * @return boolean
	 */
	function getViewable() {
		return $this->getData('viewable');
	}

	/**
	 * set viewable
	 * @param $viewable boolean
	 */
	function setViewable($viewable) {
		return $this->setData('viewable', $viewable);
	}

	/**
	 * Set the comment
	 * @param $comment string
	 * @param $locale string
	 */
	function setComment($comment, $locale) {
		$this->setData('comment', $comment, $locale);
	}

	/**
	 * Get the comment
	 * @param $locale string
	 * @return string
	 */
	function getComment($locale) {
		return $this->getData('comment', $locale);
	}

	/**
	 * Get the localized comment
	 * @return string
	 */
	function getLocalizedComment() {
		return $this->getLocalizedData('comment');
	}

	/**
	 * Set the subject
	 * @param $subject string
	 * @param $locale string
	 */
	function setSubject($subject, $locale) {
		$this->setData('subject', $subject, $locale);
	}

	/**
	 * Get the subject
	 * @param $locale string
	 * @return string
	 */
	function getSubject($locale) {
		return $this->getData('subject', $locale);
	}

	/**
	 * Get the localized subject
	 * @return string
	 */
	function getLocalizedSubject() {
		return $this->getLocalizedData('subject');
	}
}

?>
