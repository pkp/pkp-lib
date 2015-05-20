<?php

/**
 * @file classes/query/Query.inc.php
 *
 * Copyright (c) 2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class Query
 * @ingroup submission
 * @see QueryDAO
 *
 * @brief Class for Query.
 */

import('lib.pkp.classes.note.NoteDAO'); // Constants

class Query extends DataObject {
	/**
	 * Constructor.
	 */
	function Query() {
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
	 * Get the "head" (first) note for this query.
	 * @return Note
	 */
	function getHeadNote() {
		$notes = $this->getReplies();
		$note = $notes->next();
		$notes->close();
		return $note;
	}

	/**
	 * Get all notes on a query.
	 * @param $userId int Optional user ID
	 * @param $sortBy int Optional NOTE_ORDER_...
	 * @param $sortOrder int Optional SORT_DIRECTION_...
	 * @return DAOResultFactory
	 */
	function getReplies($userId = null, $sortBy = NOTE_ORDER_ID, $sortOrder = SORT_DIRECTION_ASC) {
		$noteDao = DAORegistry::getDAO('NoteDAO');
		return $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $this->getId(), null, $sortBy, $sortOrder);
	}
}

?>
