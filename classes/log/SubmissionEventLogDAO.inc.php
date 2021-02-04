<?php

/**
 * @file classes/log/SubmissionEventLogDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionEventLogDAO
 * @ingroup log
 * @see EventLogDAO
 *
 * @brief Extension to EventLogDAO for submission-specific log entries.
 */

import('lib.pkp.classes.log.EventLogDAO');
import('classes.log.SubmissionEventLogEntry');

class SubmissionEventLogDAO extends EventLogDAO {

	/**
	 * Generate a new DataObject
	 * @return SubmissionEventLogEntry
	 */
	function newDataObject() {
		$returner = new SubmissionEventLogEntry();
		$returner->setAssocType(ASSOC_TYPE_SUBMISSION);
		return $returner;
	}

	/**
	 * Get submission event log entries by submission ID
	 * @param $submissionId int
	 * @return DAOResultFactory
	 */
	function getBySubmissionId($submissionId) {
		return $this->getByAssoc(ASSOC_TYPE_SUBMISSION, $submissionId);
	}
}


