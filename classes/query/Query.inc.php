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
		$noteDao = DAORegistry::getDAO('NoteDAO');
		$notes = $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $this->getId(), null, NOTE_ORDER_ID, SORT_DIRECTION_ASC);
		$note = $notes->next();
		$notes->close();
		return $note;
	}
}

?>
