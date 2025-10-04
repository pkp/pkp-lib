<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantGridHandler
 * @ingroup controllers_grid_users_stageParticipant
 *
 * @brief Handle stageParticipant grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.CategoryGridHandler');

// import stageParticipant grid specific classes
import('lib.pkp.controllers.grid.users.stageParticipant.StageParticipantGridRow');
import('lib.pkp.controllers.grid.users.stageParticipant.StageParticipantGridCategoryRow');
import('classes.log.SubmissionEventLogEntry'); // App-specific.

import('lib.pkp.classes.linkAction.request.RemoteActionConfirmationModal');

class StageParticipantGridHandler extends CategoryGridHandler {
	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();

		// Assistants get read-only access
		$this->addRoleAssignment(
			array(ROLE_ID_ASSISTANT),
			$peOps = array('fetchGrid', 'fetchCategory', 'fetchRow', 'viewNotify', 'fetchTemplateBody', 'sendNotification')
		);

		// Managers and Editors additionally get administrative access
		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
			array_merge($peOps, array('addParticipant', 'saveParticipant', 'fetchUserList', 'removeParticipant', 'removeStageAssignment'))
		);
		$this->setTitle('editor.submission.stageParticipants');
	}


	//
	// Getters/Setters
	//
	/**
	 * Get the authorized submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * Get the authorized workflow stage.
	 * @return integer
	 */
	function getStageId() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
	}

	//
	// Overridden methods from PKPHandler
	//
	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = (int) $request->getUserVar('stageId');
		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Determine whether the current user has admin priveleges for this
	 * grid.
	 * @return boolean
	 */
	protected function _canAdminister() {
		// If the current role set includes Manager or Editor, grant.
		return (boolean) array_intersect(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR),
			$this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES)
		);
	}


	/**
	 * @copydoc CategoryGridHandler::initialize()
	 */
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Load submission-specific translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_APP_EDITOR,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_APP_DEFAULT,
			LOCALE_COMPONENT_PKP_DEFAULT,
			LOCALE_COMPONENT_PKP_SUBMISSION
		);

		// Columns
		import('lib.pkp.controllers.grid.users.stageParticipant.StageParticipantGridCellProvider');
		$cellProvider = new StageParticipantGridCellProvider();
		$this->addColumn(new GridColumn(
			'participants',
			null,
			null,
			null,
			$cellProvider
		));
		$submission = $this->getSubmission();
		$submissionId = $submission->getId();
		if (Validation::isLoggedInAs()) {
			$router = $request->getRouter();
			$dispatcher = $router->getDispatcher();
			$user = $request->getUser();
			$redirectUrl = $dispatcher->url(
				$request,
				ROUTE_PAGE,
				null,
				'workflow',
				'access',
				$submissionId
			);
			import('lib.pkp.classes.linkAction.request.RedirectAction');
			$this->addAction(
				new LinkAction(
					'signOutAsUser',
					new RedirectAction(
						$dispatcher->url($request, ROUTE_PAGE, null, 'login', 'signOutAsUser', null, array('redirectUrl' => $redirectUrl))
					),
					__('user.logOutAs', ['username' => $user->getUsername()]),
					null,
					__('user.logOutAs', ['username' => $user->getUsername()])
				)
			);
		}

		// The "Add stage participant" grid action is available to
		// Editors and Managers only
		if ($this->_canAdminister()) {
			$router = $request->getRouter();
			$this->addAction(
				new LinkAction(
					'requestAccount',
					new AjaxModal(
						$router->url($request, null, null, 'addParticipant', null, $this->getRequestArgs()),
						__('editor.submission.addStageParticipant'),
						'modal_add_user'
					),
					__('common.assign'),
					'add_user'
				)
			);
		}

		$this->setEmptyCategoryRowText('editor.submission.noneAssigned');
	}


	//
	// Overridden methods from [Category]GridHandler
	//
	/**
	 * @copydoc CategoryGridHandler::loadCategoryData()
	 */
	function loadCategoryData($request, &$userGroup, $filter = null) {
		// Retrieve useful objects.
		$submission = $this->getSubmission();
		$stageId = $this->getStageId();

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId(
			$submission->getId(),
			$stageId,
			$userGroup->getId()
		);

		return $stageAssignments->toAssociativeArray();
	}

	/**
	 * @copydoc GridHandler::isSubComponent()
	 */
	function getIsSubcomponent() {
		return true;
	}

	/**
	 * @copydoc GridHandler::getRowInstance()
	 */
	protected function getRowInstance() {
		return new StageParticipantGridRow($this->getSubmission(), $this->getStageId(), $this->_canAdminister());
	}

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowInstance()
	 */
	protected function getCategoryRowInstance() {
		$submission = $this->getSubmission();
		return new StageParticipantGridCategoryRow($submission, $this->getStageId());
	}

	/**
	 * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
	 */
	function getCategoryRowIdParameterName() {
		return 'userGroupId';
	}

	/**
	 * @copydoc GridHandler::getRequestArgs()
	 */
	function getRequestArgs() {
		$submission = $this->getSubmission();
		return array_merge(
			parent::getRequestArgs(),
			array(
				'submissionId' => $submission->getId(),
				'stageId' => $this->getStageId(),
			)
		);
	}

	/**
	 * @copydoc GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		$submission = $this->getSubmission();
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId(
			$this->getSubmission()->getId(),
			$this->getStageId()
		);

		// Make a list of the active (non-reviewer) user groups.
		$userGroupIds = array();
		while ($stageAssignment = $stageAssignments->next()) {
			$userGroupIds[] = $stageAssignment->getUserGroupId();
		}

		// Fetch the desired user groups as objects.
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$context = $request->getContext();
		$result = array();
		$userGroups = $userGroupDao->getUserGroupsByStage(
			$request->getContext()->getId(),
			$this->getStageId()
		);
		while ($userGroup = $userGroups->next()) {
			if ($userGroup->getRoleId() == ROLE_ID_REVIEWER) continue;
			if (!in_array($userGroup->getId(), $userGroupIds)) continue;
			$result[$userGroup->getId()] = $userGroup;
		}
		return $result;
	}


	//
	// Public actions
	//
	/**
	 * Add a participant to the stages
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function addParticipant($args, $request) {
		$submission = $this->getSubmission();
		$stageId = $this->getStageId();
		$assignmentId = null;
		if (array_key_exists('assignmentId', $args)) {
			$assignmentId = $args['assignmentId'];
		}
		$userGroups = $this->getGridDataElements($request);

		import('lib.pkp.controllers.grid.users.stageParticipant.form.AddParticipantForm');
		$form = new AddParticipantForm($submission, $stageId, $assignmentId);
		$form->initData();

		return new JSONMessage(true, $form->fetch($request));
	}

	/**
	 * Update the row for the current userGroup's stage participant list.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function saveParticipant($args, $request) {
		$submission = $this->getSubmission();
		$stageId = $this->getStageId();
		$assignmentId = $args['assignmentId'];
		$userGroups = $this->getGridDataElements($request);

		import('lib.pkp.controllers.grid.users.stageParticipant.form.AddParticipantForm');
		$form = new AddParticipantForm($submission, $stageId, $assignmentId);
		$form->readInputData();
		if ($form->validate()) {
			list($userGroupId, $userId, $stageAssignmentId) = $form->execute();

			$notificationMgr = new NotificationManager();

			// Check user group role id.
			$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
			$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */

			$userGroup = $userGroupDao->getById($userGroupId);
			import('classes.workflow.EditorDecisionActionsManager');
			if ($userGroup->getRoleId() == ROLE_ID_MANAGER) {
				$notificationMgr->updateNotification(
					$request,
					(new EditorDecisionActionsManager())->getStageNotifications(),
					null,
					ASSOC_TYPE_SUBMISSION,
					$submission->getId()
				);
			}

			$stages = Application::getApplicationStages();
			foreach ($stages as $workingStageId) {
				// remove the 'editor required' task if we now have an editor assigned
				if ($stageAssignmentDao->editorAssignedToStage($submission->getId(), $workingStageId)) {
					$notificationDao = DAORegistry::getDAO('NotificationDAO'); /* @var $notificationDao NotificationDAO */
					$notificationDao->deleteByAssoc(ASSOC_TYPE_SUBMISSION, $submission->getId(), null, NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED);
				}
			}

			// Create trivial notification.
			$user = $request->getUser();
			if ($stageAssignmentId != $assignmentId) { // New assignment added
				$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.addedStageParticipant')));
			} else {
				$notificationMgr->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.editStageParticipant')));
			}


			// Log addition.
			$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
			$assignedUser = $userDao->getById($userId);
			import('lib.pkp.classes.log.SubmissionLog');
			SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_ADD_PARTICIPANT, 'submission.event.participantAdded', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));

			return DAO::getDataChangedEvent($userGroupId);
		} else {
			return new JSONMessage(true, $form->fetch($request));
		}
	}

	/**
	 * Show a confirmation form to remove a stage participant, with optional email notification.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	function removeParticipant($args, $request) {
		$submission = $this->getSubmission();
		$assignmentId = (int) $request->getUserVar('assignmentId');

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignment = $stageAssignmentDao->getById($assignmentId);
		if (!$stageAssignment || $stageAssignment->getSubmissionId() != $submission->getId()) {
			return new JSONMessage(false);
		}

		import('lib.pkp.controllers.grid.users.stageParticipant.form.RemoveParticipantForm');
		$form = new RemoveParticipantForm($submission, $stageAssignment, $this->getStageId());
		$form->initData();
		return new JSONMessage(true, $form->fetch($request));
	}

	/**
	 * Handle removal form submission: optionally email the user and then remove the assignment.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage
	 */
	function removeStageAssignment($args, $request) {
		$submission = $this->getSubmission();
		$stageId = $this->getStageId();
		$assignmentId = (int) $request->getUserVar('assignmentId');

		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /* @var $stageAssignmentDao StageAssignmentDAO */
		$stageAssignment = $stageAssignmentDao->getById($assignmentId);
		if (!$request->checkCSRF() || !$stageAssignment || $stageAssignment->getSubmissionId() != $submission->getId()) {
			return new JSONMessage(false);
		}

		import('lib.pkp.controllers.grid.users.stageParticipant.form.RemoveParticipantForm');
		$form = new RemoveParticipantForm($submission, $stageAssignment, $stageId);
		$form->readInputData();
		if (!$form->validate()) {
			return new JSONMessage(true, $form->fetch($request));
		}

		$form->execute();

		$stageAssignmentDao->deleteObject($stageAssignment);

		$notificationMgr = new NotificationManager();

		$userDao = DAORegistry::getDAO('UserDAO'); /* @var $userDao UserDAO */
		$assignedUser = $userDao->getById($stageAssignment->getUserId());
		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId());
		import('lib.pkp.classes.log.SubmissionLog');
		SubmissionLog::logEvent($request, $submission, SUBMISSION_LOG_REMOVE_PARTICIPANT, 'submission.event.participantRemoved', array('name' => $assignedUser->getFullName(), 'username' => $assignedUser->getUsername(), 'userGroupName' => $userGroup->getLocalizedName()));

		$currentUser = $request->getUser();
		$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS);

		return DAO::getDataChangedEvent($stageAssignment->getUserGroupId());
	}

	/**
	 * Get the list of users for the specified user group
	 * @param $args array
	 * @param $request Request
	 * @return JSONMessage JSON object
	 */
	function fetchUserList($args, $request) {
		$submission = $this->getSubmission();
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);

		$userGroupId = (int) $request->getUserVar('userGroupId');

		$userStageAssignmentDao = DAORegistry::getDAO('UserStageAssignmentDAO'); /* @var $userStageAssignmentDao UserStageAssignmentDAO */
		$users = $userStageAssignmentDao->getUsersNotAssignedToStageInUserGroup($submission->getId(), $stageId, $userGroupId);

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /* @var $userGroupDao UserGroupDAO */
		$userGroup = $userGroupDao->getById($userGroupId);
		$roleId = $userGroup->getRoleId();

		$sectionId = $submission->getSectionId();
		$contextId = $submission->getContextId();

		$userList = array();
		while($user = $users->next()) $userList[$user->getId()] = $user->getFullName();
		if (count($userList) == 0) {
			$userList[0] = __('common.noMatches');
		}

		return new JSONMessage(true, $userList);
	}

	/**
	 * Display the notify tab.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function viewNotify($args, $request) {
		$this->setupTemplate($request);

		import('controllers.grid.users.stageParticipant.form.StageParticipantNotifyForm'); // exists in each app.
		$notifyForm = new StageParticipantNotifyForm($this->getSubmission()->getId(), ASSOC_TYPE_SUBMISSION, $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE));
		$notifyForm->initData();

		return new JSONMessage(true, $notifyForm->fetch($request));
	}

	/**
	 * Send a notification from the notify tab.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function sendNotification($args, $request) {
		$this->setupTemplate($request);

		import('controllers.grid.users.stageParticipant.form.StageParticipantNotifyForm'); // exists in each app.
		$notifyForm = new StageParticipantNotifyForm($this->getSubmission()->getId(), ASSOC_TYPE_SUBMISSION, $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE));
		$notifyForm->readInputData();

		if ($notifyForm->validate()) {
			$noteId = $notifyForm->execute();

			if ($this->getStageId() == WORKFLOW_STAGE_ID_EDITING ||
				$this->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {

				// Update submission notifications
				$notificationMgr = new NotificationManager();
				$notificationMgr->updateNotification(
					$request,
					array(
						NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
						NOTIFICATION_TYPE_AWAITING_COPYEDITS,
						NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
						NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
					),
					null,
					ASSOC_TYPE_SUBMISSION,
					$this->getSubmission()->getId()
				);
			}

			$json = new JSONMessage(true);
			$json->setGlobalEvent('stageStatusUpdated');
			return $json;
		} else {
			// Return a JSON string indicating failure
			return new JSONMessage(false);
		}
	}

	/**
	 * Fetches an email template's message body and returns it via AJAX.
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function fetchTemplateBody($args, $request) {
		$templateId = $request->getUserVar('template');
		import('lib.pkp.classes.mail.SubmissionMailTemplate');
		$template = new SubmissionMailTemplate($this->getSubmission(), $templateId);
		if ($template) {
			$user = $request->getUser();
			$dispatcher = $request->getDispatcher();
			$context = $request->getContext();
			$template->assignParams(array(
				'editorialContactSignature' => $user->getContactSignature(),
				'signatureFullName' => htmlspecialchars($user->getFullname()),
			));
			$template->replaceParams();

			import('controllers.grid.users.stageParticipant.form.StageParticipantNotifyForm'); // exists in each app.
			$notifyForm = new StageParticipantNotifyForm($this->getSubmission()->getId(), ASSOC_TYPE_SUBMISSION, $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE));
			return new JSONMessage(
				true,
				array(
					'body' => $template->getBody(),
					'variables' => $notifyForm->getEmailVariableNames($templateId),
				)
			);
		}
	}

	/**
	 * Get the js handler for this component.
	 * @return string
	 */
	public function getJSHandler() {
		return '$.pkp.controllers.grid.users.stageParticipant.StageParticipantGridHandler';
	}
}