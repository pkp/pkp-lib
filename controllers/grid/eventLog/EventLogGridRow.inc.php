<?php

/**
 * @file controllers/grid/eventLog/EventLogGridRow.inc.php
 *
 * Copyright (c) 2014 Simon Fraser University Library
 * Copyright (c) 2000-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EventLogGridRow
 * @ingroup controllers_grid_eventLog
 *
 * @brief EventLog grid row definition
 */

// Parent class
import('lib.pkp.classes.controllers.grid.GridRow');

// Other classes used
import('lib.pkp.classes.log.SubmissionFileEventLogEntry');
import('lib.pkp.controllers.api.file.linkAction.DownloadFileLinkAction');

class EventLogGridRow extends GridRow {
	/** @var Submission **/
	var $_submission;

	/**
	 * Constructor
	 */
	function EventLogGridRow($submission) {
		$this->_submission = $submission;
		parent::GridRow();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @see GridRow::initialize()
	 */
	function initialize($request) {
		parent::initialize($request);

		$logEntry = $this->getData(); // a Category object
		assert($logEntry != null && is_a($logEntry, 'EventLogEntry'));

		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$params = $logEntry->getParams();

		switch ($logEntry->getEventType()) {
			case SUBMISSION_LOG_FILE_REVISION_UPLOAD:
			case SUBMISSION_LOG_FILE_UPLOAD:
				$submissionFile = $submissionFileDao->getRevision($params['fileId'], $params['fileRevision']);
				if ($submissionFile) $this->addAction(new DownloadFileLinkAction($request, $submissionFile, null, __('common.download')));
				break;
		}
	}
}

?>
