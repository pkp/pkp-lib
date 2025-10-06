<?php

/**
 * @file controllers/grid/users/stageParticipant/StageParticipantGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class StageParticipantGridHandler
 *
 * @ingroup controllers_grid_users_stageParticipant
 *
 * @brief Handle stageParticipant grid requests.
 */

namespace PKP\controllers\grid\users\stageParticipant;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\CategoryGridHandler;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\queries\traits\StageMailable;
use PKP\controllers\grid\users\stageParticipant\form\AddParticipantForm;
use PKP\controllers\grid\users\stageParticipant\form\RemoveParticipantForm;
use PKP\controllers\grid\users\stageParticipant\form\PKPStageParticipantNotifyForm;
use PKP\core\Core;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RedirectAction;
use PKP\notification\NotificationDAO;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\PKPNotification;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\stageAssignment\StageAssignmentDAO;

class StageParticipantGridHandler extends CategoryGridHandler
{
    use StageMailable;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        // Assistants get read-only access
        $this->addRoleAssignment(
            [Role::ROLE_ID_ASSISTANT],
            $peOps = ['fetchGrid', 'fetchCategory', 'fetchRow', 'viewNotify', 'fetchTemplateBody', 'sendNotification']
        );

        // Managers and Editors additionally get administrative access
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            array_merge($peOps, ['addParticipant', 'saveParticipant', 'fetchUserList', 'removeParticipant', 'removeStageAssignment'])
        );
        $this->setTitle('editor.submission.stageParticipants');
    }


    //
    // Getters/Setters
    //
    /**
     * Get the authorized submission.
     *
     * @return Submission
     */
    public function getSubmission()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the authorized workflow stage.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
    }

    //
    // Overridden methods from PKPHandler
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $stageId = (int) $request->getUserVar('stageId');
        $this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Determine whether the current user has admin privileges for this
     * grid.
     *
     * @return bool
     */
    protected function _canAdminister()
    {
        // If the current role set includes Manager or Editor, grant.
        return (bool) array_intersect(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES)
        );
    }


    /**
     * @copydoc CategoryGridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Columns
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
        if (Validation::loggedInAs()) {
            $router = $request->getRouter();
            $dispatcher = $router->getDispatcher();
            $user = $request->getUser();
            $redirectUrl = $dispatcher->url(
                $request,
                PKPApplication::ROUTE_PAGE,
                null,
                'workflow',
                'access',
                $submissionId
            );
            $this->addAction(
                new LinkAction(
                    'signOutAsUser',
                    new RedirectAction(
                        $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'login', 'signOutAsUser', null, ['redirectUrl' => $redirectUrl])
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
     *
     * @param null|mixed $filter
     */
    public function loadCategoryData($request, &$userGroup, $filter = null)
    {
        // Retrieve useful objects.
        $submission = $this->getSubmission();
        $stageId = $this->getStageId();

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
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
    public function getIsSubcomponent()
    {
        return true;
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     */
    protected function getRowInstance()
    {
        return new StageParticipantGridRow($this->getSubmission(), $this->getStageId(), $this->_canAdminister());
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowInstance()
     */
    protected function getCategoryRowInstance()
    {
        $submission = $this->getSubmission();
        return new StageParticipantGridCategoryRow($submission, $this->getStageId());
    }

    /**
     * @copydoc CategoryGridHandler::getCategoryRowIdParameterName()
     */
    public function getCategoryRowIdParameterName()
    {
        return 'userGroupId';
    }

    /**
     * @copydoc GridHandler::getRequestArgs()
     */
    public function getRequestArgs()
    {
        $submission = $this->getSubmission();
        return array_merge(
            parent::getRequestArgs(),
            [
                'submissionId' => $submission->getId(),
                'stageId' => $this->getStageId(),
            ]
        );
    }

    /**
     * @copydoc GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        $submission = $this->getSubmission();
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndStageId(
            $this->getSubmission()->getId(),
            $this->getStageId()
        );

        // Make a list of the active (non-reviewer) user groups.
        $userGroupIds = [];
        while ($stageAssignment = $stageAssignments->next()) {
            $userGroupIds[] = $stageAssignment->getUserGroupId();
        }

        // Fetch the desired user groups as objects.
        $result = [];
        $userGroups = Repo::userGroup()->getUserGroupsByStage(
            $request->getContext()->getId(),
            $this->getStageId()
        );
        foreach ($userGroups as $userGroup) {
            if ($userGroup->getRoleId() == Role::ROLE_ID_REVIEWER) {
                continue;
            }
            if (!in_array($userGroup->getId(), $userGroupIds)) {
                continue;
            }
            $result[$userGroup->getId()] = $userGroup;
        }
        return $result;
    }


    //
    // Public actions
    //
    /**
     * Add a participant to the stages
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function addParticipant($args, $request)
    {
        $submission = $this->getSubmission();
        $stageId = $this->getStageId();
        $assignmentId = null;
        if (array_key_exists('assignmentId', $args)) {
            $assignmentId = $args['assignmentId'];
        }
        $userGroups = $this->getGridDataElements($request);

        $form = new AddParticipantForm($submission, $stageId, $assignmentId);
        $form->initData();

        return new JSONMessage(true, $form->fetch($request));
    }

    /**
     * Update the row for the current userGroup's stage participant list.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function saveParticipant($args, $request)
    {
        $submission = $this->getSubmission();
        $stageId = $this->getStageId();
        $assignmentId = $args['assignmentId'];
        $userGroups = $this->getGridDataElements($request);

        $form = new AddParticipantForm($submission, $stageId, $assignmentId);
        $form->readInputData();
        if ($form->validate()) {
            [$userGroupId, $userId, $stageAssignmentId] = $form->execute();

            $notificationMgr = new NotificationManager();

            // Check user group role id.
            $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */

            $userGroup = Repo::userGroup()->get($userGroupId);
            if ($userGroup->getRoleId() == Role::ROLE_ID_MANAGER) {
                $notificationMgr->updateNotification(
                    $request,
                    $notificationMgr->getDecisionStageNotifications(),
                    null,
                    Application::ASSOC_TYPE_SUBMISSION,
                    $submission->getId()
                );
            }

            $stages = Application::getApplicationStages();
            foreach ($stages as $workingStageId) {
                // remove the 'editor required' task if we now have an editor assigned
                if ($stageAssignmentDao->editorAssignedToStage($submission->getId(), $workingStageId)) {
                    $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
                    $notificationDao->deleteByAssoc(Application::ASSOC_TYPE_SUBMISSION, $submission->getId(), null, PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED);
                }
            }

            // Create trivial notification.
            $user = $request->getUser();
            if ($stageAssignmentId != $assignmentId) { // New assignment added
                $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.addedStageParticipant')]);
            } else {
                $notificationMgr->createTrivialNotification($user->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('notification.editStageParticipant')]);
            }


            // Log addition.
            $assignedUser = Repo::user()->get($userId, true);
            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_ADD_PARTICIPANT,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => 'submission.event.participantAdded',
                'isTranslated' => false,
                'dateLogged' => Core::getCurrentDate(),
                'userFullName' => $assignedUser->getFullName(),
                'username' => $assignedUser->getUsername(),
                'userGroupName' => $userGroup->getData('name')
            ]);
            Repo::eventLog()->add($eventLog);

            return \PKP\db\DAO::getDataChangedEvent($userGroupId);
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

		$form = new RemoveParticipantForm($submission, $stageAssignment, $stageId);
		$form->readInputData();
		if (!$form->validate()) {
			return new JSONMessage(true, $form->fetch($request));
		}

		$form->execute();

		$stageAssignmentDao->deleteObject($stageAssignment);

		$notificationMgr = new NotificationManager();

		$assignedUser = Repo::user()->get($stageAssignment->getUserId(), true);
        $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());

        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REMOVE_PARTICIPANT,
            'userId' => Validation::loggedInAs() ?? $request->getUser()->getId(),
            'message' => 'submission.event.participantRemoved',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'userFullName' => $assignedUser->getFullName(),
            'username' => $assignedUser->getUsername(),
            'userGroupName' => $userGroup->getData('name')
        ]);
        Repo::eventLog()->add($eventLog);

		$currentUser = $request->getUser();
		$notificationMgr->createTrivialNotification($currentUser->getId(), NOTIFICATION_TYPE_SUCCESS);

		return \PKP\db\DAO::getDataChangedEvent($stageAssignment->getUserGroupId());
	}
    
    /**
     * Get the list of users for the specified user group
     *
     * @param array $args
     * @param Request $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchUserList($args, $request)
    {
        $submission = $this->getSubmission();
        $stageId = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);

        $userGroupId = (int) $request->getUserVar('userGroupId');

        $collector = Repo::user()->getCollector();
        $collector->filterExcludeSubmissionStage($submission->getId(), $stageId, $userGroupId);
        $users = $collector->getMany();

        $userGroup = Repo::userGroup()->get($userGroupId);
        $roleId = $userGroup->getRoleId();

        $sectionId = $submission->getSectionId();
        $contextId = $submission->getContextId();

        $userList = [];
        foreach ($users as $user) {
            $userList[$user->getId()] = $user->getFullName();
        }
        if (count($userList) == 0) {
            $userList[0] = __('common.noMatches');
        }

        return new JSONMessage(true, $userList);
    }

    /**
     * Display the notify tab.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function viewNotify($args, $request)
    {
        $this->setupTemplate($request);

        $notifyForm = new PKPStageParticipantNotifyForm($this->getSubmission()->getId(), Application::ASSOC_TYPE_SUBMISSION, $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE));
        $notifyForm->initData();

        return new JSONMessage(true, $notifyForm->fetch($request));
    }

    /**
     * Send a notification from the notify tab.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function sendNotification($args, $request)
    {
        $this->setupTemplate($request);

        $notifyForm = new PKPStageParticipantNotifyForm($this->getSubmission()->getId(), Application::ASSOC_TYPE_SUBMISSION, $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE));
        $notifyForm->readInputData();

        if ($notifyForm->validate()) {
            $noteId = $notifyForm->execute();

            if ($this->getStageId() == WORKFLOW_STAGE_ID_EDITING ||
                $this->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {
                // Update submission notifications
                $notificationMgr = new NotificationManager();
                $notificationMgr->updateNotification(
                    $request,
                    [
                        PKPNotification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                        PKPNotification::NOTIFICATION_TYPE_AWAITING_COPYEDITS,
                        PKPNotification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
                        PKPNotification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
                    ],
                    null,
                    Application::ASSOC_TYPE_SUBMISSION,
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
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function fetchTemplateBody($args, $request)
    {
        $templateKey = $request->getUserVar('template');
        $context = $request->getContext();
        $template = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);
        if ($template) {
            $submission = $this->getSubmission();
            $mailable = $this->getStageMailable($context, $submission);
            $mailable->sender($request->getUser());
            $data = $mailable->getData();

            $notifyForm = new PKPStageParticipantNotifyForm($submission->getId(), Application::ASSOC_TYPE_SUBMISSION, $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE));
            return new JSONMessage(
                true,
                [
                    'body' => Mail::compileParams($template->getLocalizedData('body'), $data),
                    'variables' => $notifyForm->getEmailVariableNames($templateKey),
                ]
            );
        }
    }

    /**
     * Get the js handler for this component.
     *
     * @return string
     */
    public function getJSHandler()
    {
        return '$.pkp.controllers.grid.users.stageParticipant.StageParticipantGridHandler';
    }
}
