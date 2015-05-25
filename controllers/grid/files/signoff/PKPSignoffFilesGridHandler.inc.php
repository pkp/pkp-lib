<?php

/**
 * @file controllers/grid/files/signoff/SignoffFilesGridHandler.inc.php
 *
 * Copyright (c) 2014-2015 Simon Fraser University Library
 * Copyright (c) 2003-2015 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SignoffFilesGridHandler
 * @ingroup controllers_grid_files_signoff
 *
 * @brief Base grid for providing a list of files as categories and the requested signoffs on that file as rows.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.CategoryGridHandler');

// import copyediting grid specific classes
import('lib.pkp.controllers.grid.files.signoff.SignoffFilesGridCategoryRow');
import('lib.pkp.controllers.grid.files.signoff.SignoffGridRow');
import('lib.pkp.controllers.grid.files.signoff.SignoffGridCellProvider');
import('lib.pkp.controllers.grid.files.signoff.SignoffFilesGridCellProvider');

// Link actions
import('lib.pkp.classes.linkAction.request.AjaxModal');

class PKPSignoffFilesGridHandler extends CategoryGridHandler {
	/* @var int */
	var $_stageId;

	/* @var string */
	var $_symbolic;

	/* @var int */
	var $_fileStage;

	/* @var string */
	var $_eventType;

	/* @var int */
	var $_assocType;

	/* @var int */
	var $_assocId;


	/**
	 * Constructor
	 */
	function PKPSignoffFilesGridHandler($stageId, $fileStage, $symbolic, $eventType, $assocType = null, $assocId = null) {
		$this->_stageId = $stageId;
		$this->_fileStage = $fileStage;
		$this->_symbolic = $symbolic;
		$this->_eventType = $eventType;
		$this->_assocType = $assocType;
		$this->_assocId = $assocId;

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			array(
				'fetchGrid', 'fetchCategory', 'fetchRow', 'returnFileRow', 'returnSignoffRow',
				'addAuditor', 'saveAddAuditor', 'getAuditorAutocomplete',
				'signOffsignOff', 'deleteSignOffSignOff', 'deleteSignoff', 'viewLibrary',
				'editReminder', 'sendReminder'
			)
		);
		parent::CategoryGridHandler();
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {

		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		// push this policy to the top of the stack since policies in sub-classes depend on a valid submission object.
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->getStageId()), true);

		// If a signoff ID was specified, authorize it.
		if ($request->getUserVar('signoffId')) {
			import('classes.security.authorization.SignoffAccessPolicy'); // context specific.
			$this->addPolicy(new SignoffAccessPolicy($request, $args, $roleAssignments, SIGNOFF_ACCESS_MODIFY, $this->getStageId()));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * Configure the grid
	 * @param PKPRequest $request
	 */
	function initialize($request) {
		parent::initialize($request);

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_COMMON,
			LOCALE_COMPONENT_APP_COMMON,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_APP_SUBMISSION
		);

		$submission = $this->getSubmission();

		// Bring in file constants
		import('lib.pkp.classes.submission.SubmissionFile');

		// Grid actions
		// Action to add a file -- Adds a category row for the file
		import('lib.pkp.controllers.api.file.linkAction.AddFileLinkAction');
		$this->addAction(new AddFileLinkAction(
			$request, $submission->getId(),
			$this->getStageId(),
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT),
			null,
			$this->getFileStage(),
			$this->getAssocType(), $this->getAssocId()
		));

		// Action to signoff on a file -- Lets user interact with their own rows.
		$user = $request->getUser();
		$signoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO'); /* @var $signoffDao SubmissionFileSignoffDAO */
		$signoffFactory = $signoffDao->getAllBySubmission($submission->getId(), $this->getSymbolic(), $user->getId(), null, true);
		if (!$signoffFactory->wasEmpty()) {
			import('lib.pkp.controllers.api.signoff.linkAction.AddSignoffFileLinkAction');
			$this->addAction(new AddSignoffFileLinkAction(
				$request, $submission->getId(),
				$this->getStageId(), $this->getSymbolic(), null,
				__('submission.upload.signoff'), __('submission.upload.signoff')));
		}

		$router = $request->getRouter();

		// Action to add a user -- Adds the user as a subcategory to the files selected in its modal
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), $this->getFileStage());

		// The "Add Auditor" link should only be available if at least
		// one file already exists.
		if (!empty($submissionFiles)) {
			$this->addAction(new LinkAction(
				'addAuditor',
				new AjaxModal(
					$router->url($request, null, null, 'addAuditor', null, $this->getRequestArgs()),
					__('editor.submission.addAuditor'),
					'modal_add_user'
				),
				__('editor.submission.addAuditor'),
				'add_user'
			));
		}

		//
		// Grid Columns
		//
		$userIds = $this->_getSignoffCapableUsersId();

		// Add a column for the file's label
		$this->addColumn(
			new GridColumn(
				'name',
				'common.file',
				null,
				null,
				new SignoffGridCellProvider($submission->getId(), $this->getStageId()),
				array('alignment' => COLUMN_ALIGNMENT_LEFT, 'width' => 60)
			)
		);

		// Add the considered column (signoff).
		import('lib.pkp.controllers.grid.files.SignoffOnSignoffGridColumn');
		$this->addColumn(new SignoffOnSignoffGridColumn('common.considered', $userIds, $this->getRequestArgs(), array('hoverTitle' => true)));

		// Add approved column (make the file visible). This column
		// will only have content in category rows, so we define
		// a cell provider there. See getCategoryRowInstance().
		import('lib.pkp.classes.controllers.grid.GridColumn');
		import('lib.pkp.classes.controllers.grid.NullGridCellProvider');
		$this->addColumn(new GridColumn(
			'approved',
			'editor.signoff.approved', null, null,
			new NullGridCellProvider())
		);

		// Set the no-row locale key
		$this->setEmptyRowText('grid.noFiles');
	}


	//
	// Getters and Setters
	//
	/**
	 * Get the submission associated with this chapter grid.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}


	/**
	 * Get the workflow stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}


	/**
	 * Get the signoff's symbolic
	 * @return string
	 */
	function getSymbolic() {
		return $this->_symbolic;
	}


	/**
	 * Get the fileStage (for categories)
	 */
	function getFileStage() {
		return $this->_fileStage;
	}


	/**
	 * Get the event type
	 */
	function getEventType() {
		return $this->_eventType;
	}


	/**
	 * Get the assoc type
	 */
	function getAssocType() {
		return $this->_assocType;
	}


	/**
	 * Set the assoc Id
	 */
	function setAssocId($assocId) {
		$this->_assocId = $assocId;
	}


	/**
	 * Get the assoc id
	 */
	function getAssocId() {
		return $this->_assocId;
	}

	/**
	 * @copydoc GridDataProvider::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		$signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
		$args = array_merge(
			parent::getRequestArgs(),
			array('submissionId' => $submission->getId(),
				'stageId' => $this->getStageId())
		);

		if (is_a($signoff, 'Signoff')) {
			$args['signoffId'] = $signoff->getId();
		}

		return $args;
	}


	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		// Grab the files to display as categories
		$submission = $this->getSubmission();
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO'); /* @var $submissionFileDao SubmissionFileDAO */
		if ($this->getAssocType() && $this->getAssocId()) {
			$submissionFiles = $submissionFileDao->getLatestRevisionsByAssocId(
				$this->getAssocType(), $this->getAssocId(),
				$submission->getId(), $this->getFileStage()
			);
		} else {
			$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), $this->getFileStage());
		}

		// $submissionFiles is keyed on file and revision, for the grid we need to key on file only
		// since the grid shows only the most recent revision.
		$data = array();
		foreach ($submissionFiles as $submissionFile) {
			$data[$submissionFile->getFileId()] = $submissionFile;
		}
		return $data;
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @copydoc CategoryGridHandler::getCategoryRowInstance()
	 * @return SignoffFilesGridCategoryRow
	 */
	protected function getCategoryRowInstance() {
		$row = new SignoffFilesGridCategoryRow($this->getStageId());
		$submission = $this->getSubmission();
		$row->setCellProvider(new SignoffFilesGridCellProvider($submission->getId(), $this->getStageId()));
		$row->addFlag('gridRowStyle', true);
		return $row;
	}


	/**
	 * Get all the signoffs for this category.
	 * @copydoc CategoryGridHandler::loadCategoryData()
	 * @param $submissionFile SubmissionFile or string
	 * @return array Signoffs
	 */
	function loadCategoryData($request, $submissionFile) {
		$submissionFileSignoffDao = DAORegistry::getDAO('SubmissionFileSignoffDAO');
		if (is_a($submissionFile, 'SubmissionFile')) {
			$signoffFactory = $submissionFileSignoffDao->getAllBySymbolic($this->getSymbolic(), $submissionFile->getFileId()); /* @var $signoffs DAOResultFactory */
		} else if (is_numeric($submissionFile)) { // $submissionFile is already the file id.
			$signoffFactory = $submissionFileSignoffDao->getAllBySymbolic($this->getSymbolic(), $submissionFile); /* @var $signoffs DAOResultFactory */
		} else {
			assert(false);
		}
		$signoffs = $signoffFactory->toAssociativeArray();
		return $signoffs;
	}

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
	 */
	function getCategoryRowIdParameterName() {
		return 'fileId';
	}


	/**
	 * Get the row handler - override the default row handler
	 * @return SignoffGridRow
	 */
	protected function getRowInstance() {
		return new SignoffGridRow($this->getStageId());
	}


	//
	// Public methods
	//
	/**
	 * Adds an auditor (signoff) to a file
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addAuditor($args, $request) {
		// Identify the submission being worked on
		$submission = $this->getSubmission();

		// Form handling
		$router = $request->getRouter();
		$autocompleteUrl = $router->url($request, null, null, 'getAuditorAutocomplete', null, $this->getRequestArgs());
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('autocompleteUrl', $autocompleteUrl);

		$auditorForm = $this->_getFileAuditorForm();
		if ($auditorForm->isLocaleResubmit()) {
			$auditorForm->readInputData();
		} else {
			$auditorForm->initData($args, $request);
		}

		return new JSONMessage(true, $auditorForm->fetch($request));
	}


	/**
	 * Save the form for adding an auditor to a copyediting file
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function saveAddAuditor($args, $request) {
		// Identify the submission being worked on
		$submission = $this->getSubmission();

		// Form handling
		$auditorForm = $this->_getFileAuditorForm();
		$auditorForm->readInputData();
		if ($auditorForm->validate()) {
			$auditorForm->execute($request);

			// Create trivial notification.
			$currentUser = $request->getUser();
			NotificationManager::createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.addedAuditor')));

			// We need to refresh the whole grid because multiple files can be assigned at once.
			return DAO::getDataChangedEvent();
		}

		return new JSONMessage(false);
	}


	/**
	 * Get users for copyediting autocomplete.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function getAuditorAutocomplete($args, $request) {
		// Identify the submission we are working with
		$submission = $this->getSubmission();

		// Retrieve the users for the autocomplete control: Any user assigned to this stage
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageUsers = $stageAssignmentDao->getBySubmissionAndStageId($submission->getId(), $this->getStageId());

		$itemList = array();
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userDao = DAORegistry::getDAO('UserDAO');
		$term = $request->getUserVar('term');
		while($stageUser = $stageUsers->next()) {
			$userGroup = $userGroupDao->getById($stageUser->getUserGroupId());
			$user = $userDao->getById($stageUser->getUserId());
			$term = preg_quote($term, '/');
			if ($term == '' || preg_match('/' . $term .'/i', $user->getFullName()) || preg_match('/' . $term .'/i', $userGroup->getLocalizedName())) {
				$itemList[] = array(
					'label' =>  sprintf('%s (%s)', $user->getFullName(), $userGroup->getLocalizedName()),
					'value' => $user->getId() . '-' . $stageUser->getUserGroupId()
				);
			}
		}

		if (count($itemList) == 0) {
			return $this->noAutocompleteResults();
		}

		return new JSONMessage(true, $itemList);
	}


	/**
	 * Return a grid row with for the copyediting grid
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function returnSignoffRow($args, $request) {
		$signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);

		if($signoff) {
			return DAO::getDataChangedEvent();
		} else {
			return new JSONMessage(false, __('common.uploadFailed'));
		}
	}


	/**
	 * Delete a user's signoff
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteSignoff($args, $request) {
		$signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);

		if($signoff && !$signoff->getDateCompleted()) {

			$signoffUserId = $signoff->getUserId();
			if ($signoff->getAssocType() == ASSOC_TYPE_SUBMISSION_FILE) {
				$fileId = $signoff->getAssocId();
			}
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			$submissionFile = $submissionFileDao->getLatestRevision($fileId);

			// Remove the signoff
			$signoffDao = DAORegistry::getDAO('SignoffDAO'); /* @var $signoffDao SignoffDAO */
			$signoffDao->deleteObjectById($signoff->getId());

			// Trivial notifications.
			$user = $request->getUser();
			NotificationManager::createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.removedAuditor')));

			$notificationMgr = new NotificationManager();
			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_AUDITOR_REQUEST),
				array($signoff->getUserId()),
				ASSOC_TYPE_SIGNOFF,
				$signoff->getId()
			);

			// Delete for all users.
			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT),
				null,
				ASSOC_TYPE_SIGNOFF,
				$signoff->getId()
			);

			$notificationMgr->updateNotification(
				$request,
				array(NOTIFICATION_TYPE_SIGNOFF_COPYEDIT, NOTIFICATION_TYPE_SIGNOFF_PROOF),
				array($signoff->getUserId()),
				ASSOC_TYPE_SUBMISSION,
				$submissionFile->getSubmissionId()
			);

			// log the remove auditor event.
			import('lib.pkp.classes.log.SubmissionFileLog');
			import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
			$userDao = DAORegistry::getDAO('UserDAO');
			$signoffUser = $userDao->getById($signoffUserId);

			if (isset($signoffUser) && isset($submissionFile)) {
				SubmissionFileLog::logEvent($request, $submissionFile, SUBMISSION_LOG_FILE_AUDITOR_CLEAR, 'submission.event.fileAuditorCleared', array('file' => $submissionFile->getOriginalFileName(), 'name' => $signoffUser->getFullName(), 'username' => $signoffUser->getUsername()));
			}
			return DAO::getDataChangedEvent($signoff->getId(), $signoff->getAssocId());
		} else {
			return new JSONMessage(false, 'manager.setup.errorDeletingItem');
		}
	}


	/**
	 * Let the user signoff on the signoff
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function signOffsignOff($args, $request) {
		$rowSignoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
		if (!$rowSignoff) fatalError('Invalid Signoff given');

		$user = $request->getUser();
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$signoff = $signoffDao->build('SIGNOFF_SIGNOFF', ASSOC_TYPE_SIGNOFF, $rowSignoff->getId(), $user->getId());
		$signoff->setDateCompleted(Core::getCurrentDate());
		$signoffDao->updateObject($signoff);

		// Delete for all users.
		$notificationMgr = new NotificationManager();
		$notificationMgr->updateNotification(
			$request,
			array(NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT),
			null,
			ASSOC_TYPE_SIGNOFF,
			$signoff->getAssocId()
		);

		// log the sign off sign off
		import('lib.pkp.classes.log.SubmissionFileLog');
		import('lib.pkp.classes.log.SubmissionFileEventLogEntry'); // constants
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		$submissionFile = $submissionFileDao->getLatestRevision($rowSignoff->getAssocId());
		if (isset($submissionFile)) {
			SubmissionFileLog::logEvent($request, $submissionFile, SUBMISSION_LOG_FILE_SIGNOFF_SIGNOFF, 'submission.event.signoffSignoff', array('file' => $submissionFile->getOriginalFileName(), 'name' => $user->getFullName(), 'username' => $user->getUsername()));
		}
		// Redraw the row.
		return DAO::getDataChangedEvent($rowSignoff->getId(), $rowSignoff->getAssocId());
	}

	/**
	 * Delete the signoff on the signoff in request.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function deleteSignOffSignOff($args, $request) {
		$rowSignoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
		if (!$rowSignoff) fatalError('Invalid Signoff given');

		$user = $request->getUser();
		$signoffDao = DAORegistry::getDAO('SignoffDAO');
		$signoffOnSignoffFactory = $signoffDao->getAllByAssocType(ASSOC_TYPE_SIGNOFF, $rowSignoff->getId());
		$signoffOnSignoff = $signoffOnSignoffFactory->next();
		if (!$signoffOnSignoff) fatalError('Invalid Signoff given');

		$signoffDao->deleteObject($signoffOnSignoff);

		return DAO::getDataChangedEvent($rowSignoff->getId(), $rowSignoff->getAssocId());
	}


	/**
	 * Load the (read only) file library.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewLibrary($args, $request) {

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('canEdit', false);
		return $templateMgr->fetchJson('controllers/tab/settings/library.tpl');
	}

	/**
	 * Displays a modal to allow the editor to enter a message to send to the auditor as a reminder.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function editReminder($args, $request) {
		// Identify the signoff.
		$signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
		$submission = $this->getSubmission();

		// Initialize form.
		$auditorReminderForm = $this->_getAuditorReminderForm();
		$auditorReminderForm->initData($args, $request);

		// Render form.
		return new JSONMessage(true, $auditorReminderForm->fetch($request));
	}

	/**
	 * Send the auditor reminder and close the modal.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return string Serialized JSON object
	 */
	function sendReminder($args, $request) {
		$signoff = $this->getAuthorizedContextObject(ASSOC_TYPE_SIGNOFF);
		$submission = $this->getSubmission();

		// Form handling
		$auditorReminderForm = $this->_getAuditorReminderForm();
		$auditorReminderForm->readInputData();
		if ($auditorReminderForm->validate()) {
			$auditorReminderForm->execute($args, $request);

			// Insert a trivial notification to indicate the auditor was reminded successfully.
			$currentUser = $request->getUser();
			$notificationMgr = new NotificationManager();
			$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.sentNotification')));
			return new JSONMessage(true);
		} else {
			return new JSONMessage(false, __('editor.review.reminderError'));
		}
	}


	//
	// Private helper methods.
	//
	/**
	 * Get all ids of users that are capable of signing off a signoff.
	 * @return array
	 */
	function _getSignoffCapableUsersId() {
		$submission = $this->getSubmission();

		// Get all the users that are assigned to the stage (managers, sub editors, and assistants)
		// FIXME: is there a better way to do this?
		$userIds = array();
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$subEditorAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_SUB_EDITOR, $this->getStageId());
		$assistantAssignments = $stageAssignmentDao->getBySubmissionAndRoleId($submission->getId(), ROLE_ID_ASSISTANT, $this->getStageId());

		$allAssignments = array_merge(
			$subEditorAssignments->toArray(),
			$assistantAssignments->toArray()
		);

		foreach ($allAssignments as $assignment) {
			$userIds[] = $assignment->getUserId();
		}

		// We need to manually include the editor, because he has access
		// to all submission and its workflow stages but not always with
		// an stage assignment (editorial and production stages, for example).
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$managerUserGroupsFactory = $userGroupDao->getByRoleId($submission->getContextId(), ROLE_ID_MANAGER);
		while ($userGroup = $managerUserGroupsFactory->next()) {
			$usersFactory = $userGroupDao->getUsersById($userGroup->getId(), $submission->getContextId());
			while ($user = $usersFactory->next()) {
				$userIds[] = $user->getId();
			}
		}

		return array_unique($userIds);
	}

	/**
	 * return a context-specific instance of the auditor reminder form for this grid.
	 * @return Form
	 */
	function _getAuditorReminderForm() {
		assert(false); // overridden in subclasses.
	}

	/**
	 * returns a context-specific instance of the file auditor form for this grid.
	 */
	function _getFileAuditorForm() {
		assert(false); // overridden in subclasses.
	}
}

?>
