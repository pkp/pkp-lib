<?php
/**
 * @file classes/file/PKPFileManagementHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPFileManagementHandler
 * @ingroup classes_file
 *
 * @brief An abstract class that handles common functionality
 *  for controllers that manage files.
 */

// Import the base Handler.
import('classes.handler.Handler');

class PKPFileManagementHandler extends Handler {
	/**
	 * Constructor
	 */
	function FileManagementHandler() {
		parent::Handler();
	}

	/**
	 * @see PKPHandler::initialize()
	 */
	function initialize(&$request, $args) {
		parent::initialize($request, $args);
	}


	//
	// Getters and Setters
	//
	/**
	 * The submission to which we upload files.
	 * @return Submission
	 */
	function &getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}


	/**
	 * Get the authorized workflow stage.
	 * @return integer One of the WORKFLOW_STAGE_ID_* constants.
	 */
	function getStageId() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
	}
}

?>
