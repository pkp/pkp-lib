<?php

/**
 * @file controllers/grid/eventLog/EventLogGridRow.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
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
import('lib.pkp.controllers.grid.eventLog.linkAction.EmailLinkAction');

class EventLogGridRow extends GridRow {
	/** @var Submission **/
	var $_submission;

	/** @var boolean Is the current user assigned as an author to this submission */
	var $_isCurrentUserAssignedAuthor;

	/**
	 * Constructor
	 * @param $submission Submission
	 * @param $isCurrentUserAssignedAuthor boolean Is the current user assigned
	 *  as an author to this submission?
	 */
	function __construct($submission, $isCurrentUserAssignedAuthor) {
		$this->_submission = $submission;
		$this->_isCurrentUserAssignedAuthor = $isCurrentUserAssignedAuthor;
		parent::__construct();
	}

	//
	// Overridden methods from GridRow
	//
	/**
	 * @copydoc GridRow::initialize()
	 */
	function initialize($request, $template = null) {
		parent::initialize($request, $template);

		$logEntry = $this->getData(); // a Category object
		assert($logEntry != null && (is_a($logEntry, 'EventLogEntry') || is_a($logEntry, 'EmailLogEntry')));

		if (is_a($logEntry, 'EventLogEntry')) {
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
			$params = $logEntry->getParams();

			switch ($logEntry->getEventType()) {
				case SUBMISSION_LOG_FILE_REVISION_UPLOAD:
				case SUBMISSION_LOG_FILE_UPLOAD:
					$submissionFile = $submissionFileDao->getRevision($params['fileId'], $params['fileRevision']);
					if ($submissionFile) {
						$blindAuthor = false;
						$maybeBlindAuthor = $this->_isCurrentUserAssignedAuthor && $submissionFile->getFileStage() === SUBMISSION_FILE_REVIEW_ATTACHMENT;
						if ($maybeBlindAuthor && $submissionFile->getAssocType() === ASSOC_TYPE_REVIEW_ASSIGNMENT) {
							$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /* @var $reviewAssignmentDao ReviewAssignmentDAO */
							$reviewAssignment = $reviewAssignmentDao->getById($submissionFile->getAssocId());
							if ($reviewAssignment && in_array($reviewAssignment->getReviewMethod(), array(SUBMISSION_REVIEW_METHOD_BLIND, SUBMISSION_REVIEW_METHOD_DOUBLEBLIND))) {
								$blindAuthor = true;
							}
						}
						if (!$blindAuthor) {
							$workflowStageId = $submissionFileDao->getWorkflowStageId($submissionFile);
							// If a submission file is attached to a query that has been deleted, we cannot
							// determine its stage. Don't present a download link in this case.
							if ($workflowStageId || $submissionFile->getFileStage() != SUBMISSION_FILE_QUERY) {
								$this->addAction(new DownloadFileLinkAction($request, $submissionFile, $submissionFileDao->getWorkflowStageId($submissionFile), __('common.download')));
							}
						}
					}
					break;
			}

		} elseif (is_a($logEntry, 'EmailLogEntry')) {
			$this->addAction(
				new EmailLinkAction(
					$request,
					__('submission.event.viewEmail'),
					array(
						'submissionId' => $logEntry->getAssocId(),
						'emailLogEntryId' => $logEntry->getId(),
					)
				)
			);
		}
	}
}


