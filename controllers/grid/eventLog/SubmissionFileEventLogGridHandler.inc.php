<?php

/**
 * @file controllers/grid/eventLog/SubmissionFileEventLogGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionFileEventLogGridHandler
 * @ingroup controllers_grid_eventLog
 *
 * @brief Abstract grid handler presenting the submission event log grid.
 */

// import grid base classes
import('lib.pkp.controllers.grid.eventLog.SubmissionEventLogGridHandler');

class SubmissionFileEventLogGridHandler extends SubmissionEventLogGridHandler {
	/**
	 * Constructor
	 */
	function SubmissionFileEventLogGridHandler() {
		parent::SubmissionEventLogGridHandler();
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the submission file associated with this grid.
	 * @return Submission
	 */
	function getSubmissionFile() {
		return $this->_submissionFile;
	}

	/**
	 * Set the submission file
	 * @param $submissionFile SubmissionFile
	 */
	function setSubmissionFile($submissionFile) {
		$this->_submissionFile = $submissionFile;
	}


	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('classes.security.authorization.SubmissionFileAccessPolicy');
		$this->addPolicy(new SubmissionFileAccessPolicy($request, $args, $roleAssignments, SUBMISSION_FILE_ACCESS_MODIFY));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Retrieve the authorized monograph.
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$submissionFile = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION_FILE);
		$this->setSubmissionFile($submissionFile);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * Get the arguments that will identify the data in the grid
	 * In this case, the monograph.
	 * @return array
	 */
	function getRequestArgs() {
		$submissionFile = $this->getSubmissionFile();

		return array(
			'submissionId' => $submissionFile->getSubmissionId(),
			'fileId' => $submissionFile->getFileId(),
			'revision' => $submissionFile->getRevision(),
		);
	}

	/**
	 * @copydoc GridHandler::loadData
	 */
	function loadData($request, $filter = null) {
		$submissionFile = $this->getSubmissionFile();
		$submissionFileEventLogDao = DAORegistry::getDAO('SubmissionFileEventLogDAO');
		$eventLogEntries = $submissionFileEventLogDao->getByFileId(
			$submissionFile->getFileId()
		);

		return $eventLogEntries->toArray();
	}
}

?>
