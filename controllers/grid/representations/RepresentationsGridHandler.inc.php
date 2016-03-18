<?php

/**
 * @file controllers/grid/representations/RepresentationsGridHandler.inc.php
 *
 * Copyright (c) 2014-2016 Simon Fraser University Library
 * Copyright (c) 2000-2016 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class RepresentationsGridHandler
 * @ingroup controllers_grid_representations
 *
 * @brief Handle representations grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.CategoryGridHandler');

// import format grid specific classes
import('lib.pkp.controllers.grid.representations.RepresentationsGridCategoryRow');
import('lib.pkp.controllers.grid.representations.RepresentationsCategoryGridDataProvider');
import('lib.pkp.controllers.grid.files.SubmissionFilesGridRow');
import('lib.pkp.classes.controllers.grid.files.FilesGridCapabilities');

// Link action & modal classes
import('lib.pkp.classes.linkAction.request.AjaxModal');

abstract class RepresentationsGridHandler extends CategoryGridHandler {
	/** @var Submission */
	var $_submission;

	/** @var RepresentationsGridCellProvider */
	var $_cellProvider;

	/**
	 * Constructor
	 */
	function RepresentationsGridHandler() {
		parent::CategoryGridHandler(new RepresentationsCategoryGridDataProvider($this));
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
			array(
				'fetchGrid', 'fetchRow', 'fetchCategory',
				'addFormat', 'editFormat', 'updateFormat', 'deleteFormat',
				'setApproved', 'setProofFileCompletion', 'selectFiles',
			)
		);
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the submission associated with this publication format grid.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->_submission;
	}

	/**
	 * Set the submission
	 * @param $submission Submission
	 */
	function setSubmission($submission) {
		$this->_submission = $submission;
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
		import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
		$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		// Retrieve the authorized submission.
		$this->setSubmission($this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION));

		// Load submission-specific translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_DEFAULT
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	function getRowInstance() {
		return new SubmissionFilesGridRow(
			new FilesGridCapabilities(FILE_GRID_ADD | FILE_GRID_DELETE | FILE_GRID_MANAGE | FILE_GRID_EDIT | FILE_GRID_VIEW_NOTES),
			WORKFLOW_STAGE_ID_PRODUCTION
		);
	}

	/**
	 * Get the arguments that will identify the data in the grid
	 * In this case, the submission.
	 * @return array
	 */
	function getRequestArgs() {
		return array(
			'submissionId' => $this->getSubmission()->getId(),
		);
	}


	//
	// Public grid actions
	//
	/**
	 * Add a new publication format
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addFormat($args, $request) {
		return $this->editFormat($args, $request);
	}

	/**
	 * Set the approval status for a file.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function setProofFileCompletion($args, $request) {
		$submission = $this->getSubmission();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		import('lib.pkp.classes.submission.SubmissionFile'); // Constants
		$submissionFile = $submissionFileDao->getRevision(
			$request->getUserVar('fileId'),
			$request->getUserVar('revision'),
			SUBMISSION_FILE_PROOF,
			$submission->getId()
		);
		if ($submissionFile && $submissionFile->getAssocType()==ASSOC_TYPE_REPRESENTATION) {
			// Update the approval flag
			$submissionFile->setViewable($request->getUserVar('approval')?1:0);
			$submissionFileDao->updateObject($submissionFile);

			// Log the event
			import('lib.pkp.classes.log.SubmissionFileLog');
			import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
			$user = $request->getUser();
			SubmissionFileLog::logEvent($request, $submissionFile, SUBMISSION_LOG_FILE_SIGNOFF_SIGNOFF, 'submission.event.signoffSignoff', array('file' => $submissionFile->getOriginalFileName(), 'name' => $user->getFullName(), 'username' => $user->getUsername()));

			return DAO::getDataChangedEvent();
		}
		return new JSONMessage(false);
	}

	/**
	 * Show the form to allow the user to select files from previous stages
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function selectFiles($args, $request) {
		$submission = $this->getSubmission();
		$representationDao = Application::getRepresentationDAO();
		$representation = $representationDao->getById(
			$request->getUserVar('representationId'),
			$submission->getId()
		);

		import('lib.pkp.controllers.grid.files.proof.form.ManageProofFilesForm');
		$manageProofFilesForm = new ManageProofFilesForm($submission->getId(), $representation->getId());
		$manageProofFilesForm->initData($args, $request);
		return new JSONMessage(true, $manageProofFilesForm->fetch($request));
	}
}

?>
