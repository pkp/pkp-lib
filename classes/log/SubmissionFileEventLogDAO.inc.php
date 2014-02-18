<?php

/**
 * @file classes/log/SubmissionFileEventLogDAO.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileEventLogDAO
 * @ingroup log
 * @see EventLogDAO
 *
 * @brief Extension to EventLogDAO for submission file specific log entries.
 */

import('lib.pkp.classes.log.EventLogDAO');
import('lib.pkp.classes.log.SubmissionFileEventLogEntry');

class SubmissionFileEventLogDAO extends EventLogDAO {
	/**
	 * Constructor
	 */
	function SubmissionFileEventLogDAO() {
		parent::EventLogDAO();
	}

	/**
	 * Instantiate a submission file event log entry.
	 * @return SubmissionFileEventLogEntry
	 */
	function newDataObject() {
		$returner = new SubmissionFileEventLogEntry();
		$returner->setAssocType(ASSOC_TYPE_SUBMISSION_FILE);
		return $returner;
	}

	/**
	 * Get event log entries by submission file ID.
	 * @param $fileId int
	 * @return DAOResultFactory
	 */
	function getByFileId($fileId) {
		return $this->getByAssoc(ASSOC_TYPE_SUBMISSION_FILE, $fileId);
	}
}

?>
