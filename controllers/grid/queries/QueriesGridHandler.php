<?php

/**
 * @file controllers/grid/queries/QueriesGridHandler.php
 *
 * Copyright (c) 2016-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueriesGridHandler
 *
 * @ingroup controllers_grid_query
 *
 * @brief base PKP class to handle query grid requests.
 */

namespace PKP\controllers\grid\queries;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\feature\OrderGridItemsFeature;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\queries\form\QueryForm;
use PKP\controllers\grid\queries\traits\StageMailable;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\linkAction\LinkAction;
use PKP\linkAction\request\AjaxModal;
use PKP\linkAction\request\RemoteActionConfirmationModal;
use PKP\log\SubmissionEmailLogEventType;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\query\Query;
use PKP\query\QueryParticipant;
use PKP\security\authorization\QueryAccessPolicy;
use PKP\security\authorization\QueryWorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;

class QueriesGridHandler extends GridHandler
{
    use StageMailable;

    /** @var int WORKFLOW_STAGE_ID_... */
    public $_stageId;

    /** @var PKPRequest */
    public $_request;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow', 'readQuery', 'participants', 'addQuery', 'editQuery', 'updateQuery', 'deleteQuery']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['openQuery', 'closeQuery', 'saveSequence', 'fetchTemplateBody']
        );
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN],
            ['leaveQuery']
        );
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
        return $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the authorized query.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_QUERY);
    }

    /**
     * Get the stage id.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Get the query assoc type.
     *
     * @return int Application::ASSOC_TYPE_...
     */
    public function getAssocType()
    {
        return PKPApplication::ASSOC_TYPE_SUBMISSION;
    }

    /**
     * Get the query assoc ID.
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getSubmission()->getId();
    }

    /**
     * Create and return a data provider for this grid.
     *
     * @return QueriesGridCellProvider
     */
    public function getCellProvider()
    {
        return new QueriesGridCellProvider(
            $this->getSubmission(),
            $this->getStageId(),
            $this->getAccessHelper()
        );
    }


    //
    // Overridden methods from PKPHandler.
    // Note: this is subclassed in application-specific grids.
    //
    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->_stageId = (int) $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy

        $this->_request = $request;

        if ($request->getUserVar('queryId')) {
            $this->addPolicy(new QueryAccessPolicy($request, $args, $roleAssignments, $this->_stageId));
        } else {
            $this->addPolicy(new QueryWorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $this->_stageId));
        }

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        switch ($this->getStageId()) {
            case WORKFLOW_STAGE_ID_SUBMISSION: $this->setTitle('submission.queries.submission');
                break;
            case WORKFLOW_STAGE_ID_EDITING: $this->setTitle('submission.queries.editorial');
                break;
            case WORKFLOW_STAGE_ID_PRODUCTION: $this->setTitle('submission.queries.production');
                break;
            case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
            case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                $this->setTitle('submission.queries.review');
                break;
            default: assert(false);
        }

        // Columns
        $cellProvider = $this->getCellProvider();
        $this->addColumn(new QueryTitleGridColumn($this->getRequestArgs()));

        $this->addColumn(new GridColumn(
            'from',
            'submission.query.from',
            null,
            null,
            $cellProvider,
            ['html' => true, 'width' => 20]
        ));
        $this->addColumn(new GridColumn(
            'lastReply',
            'submission.query.lastReply',
            null,
            null,
            $cellProvider,
            ['html' => true, 'width' => 20]
        ));
        $this->addColumn(new GridColumn(
            'replies',
            'submission.query.replies',
            null,
            null,
            $cellProvider,
            ['width' => 10, 'alignment' => GridColumn::COLUMN_ALIGNMENT_CENTER]
        ));

        $this->addColumn(
            new GridColumn(
                'closed',
                'submission.query.closed',
                null,
                'controllers/grid/common/cell/selectStatusCell.tpl',
                $cellProvider,
                ['width' => 10, 'alignment' => GridColumn::COLUMN_ALIGNMENT_CENTER]
            )
        );

        $router = $request->getRouter();
        if ($this->getAccessHelper()->getCanCreate($this->getStageId())) {
            $this->addAction(new LinkAction(
                'addQuery',
                new AjaxModal(
                    $router->url($request, null, null, 'addQuery', null, $this->getRequestArgs()),
                    __('grid.action.addQuery'),
                ),
                __('grid.action.addQuery'),
                'add_item'
            ));
        }
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::initFeatures()
     */
    public function initFeatures($request, $args)
    {
        $features = parent::initFeatures($request, $args);
        if ($this->getAccessHelper()->getCanOrder($this->getStageId())) {
            $features[] = new OrderGridItemsFeature();
        }
        return $features;
    }

    /**
     * @copydoc GridHandler::getDataElementSequence()
     */
    public function getDataElementSequence($row)
    {
        return $row->seq;
    }

    /**
     * @copydoc GridHandler::setDataElementSequence()
     */
    public function setDataElementSequence($request, $rowId, $gridDataElement, $newSequence)
    {
        $query = Query::where('id', $rowId)->withAssoc($this->getAssocType(), $this->getAssocId())->first();
        $query->seq = $newSequence;
        $query->save();
    }

    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return QueriesGridRow
     */
    public function getRowInstance()
    {
        return new QueriesGridRow(
            $this->getSubmission(),
            $this->getStageId(),
            $this->getAccessHelper()
        );
    }

    /**
     * Get an instance of the queries grid access helper
     *
     * @return QueriesAccessHelper
     */
    public function getAccessHelper()
    {
        return new QueriesAccessHelper($this->getAuthorizedContext(), $this->_request->getUser());
    }

    /**
     * Get the arguments that will identify the data in the grid.
     * Overridden by child grids.
     *
     * @return array
     */
    public function getRequestArgs()
    {
        return [
            'submissionId' => $this->getSubmission()->getId(),
            'stageId' => $this->getStageId(),
        ];
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    public function loadData($request, $filter = null)
    {
        $user = $this->getAccessHelper()->getCanListAll($this->getStageId()) ? null : $request->getUser()->getId();

        return Query::withAssoc($this->getAssocType(), $this->getAssocId())
            ->withStageId($this->getStageId())
            ->when($user, fn ($q) => $q->withUserId($user))
            ->lazy();
    }

    //
    // Public Query Grid Actions
    //
    /**
     * Add a query
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function addQuery($args, $request)
    {
        if (!$this->getAccessHelper()->getCanCreate($this->getStageId())) {
            return new JSONMessage(false);
        }

        $queryForm = new QueryForm(
            $request,
            $this->getAssocType(),
            $this->getAssocId(),
            $this->getStageId()
        );
        $queryForm->initData();
        return new JSONMessage(true, $queryForm->fetch($request, null, false, $this->getRequestArgs()));
    }

    /**
     * Delete a query.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteQuery($args, $request)
    {
        $query = $this->getQuery();
        if (!$request->checkCSRF() || !$query || !$this->getAccessHelper()->getCanDelete($query->id)) {
            return new JSONMessage(false);
        }

        $query->delete();
        Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $query->id)->delete();
        Notification::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $query->id)->delete();

        if ($this->getStageId() == WORKFLOW_STAGE_ID_EDITING ||
            $this->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {
            // Update submission notifications
            $notificationMgr = new NotificationManager();
            $notificationMgr->updateNotification(
                $request,
                [
                    Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                    Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS,
                    Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
                    Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
                ],
                null,
                PKPApplication::ASSOC_TYPE_SUBMISSION,
                $this->getAssocId()
            );
        }

        return DAO::getDataChangedEvent($query->id);
    }

    /**
     * Open a closed query.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function openQuery($args, $request)
    {
        $query = $this->getQuery();
        if (!$query || !$this->getAccessHelper()->getCanOpenClose($query)) {
            return new JSONMessage(false);
        }

        $query->closed = false;
        $query->save();
        return DAO::getDataChangedEvent($query->id);
    }

    /**
     * Close an open query.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function closeQuery($args, $request)
    {
        $query = $this->getQuery();
        if (!$query || !$this->getAccessHelper()->getCanOpenClose($query)) {
            return new JSONMessage(false);
        }

        $query->closed = true;
        $query->save();
        return DAO::getDataChangedEvent($query->id);
    }

    /**
     * Get the name of the query notes grid handler.
     *
     * @return string
     */
    public function getQueryNotesGridHandlerName()
    {
        return 'grid.queries.QueryNotesGridHandler';
    }

    /**
     * Read a query
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function readQuery($args, $request)
    {
        $query = $this->getQuery();
        $router = $request->getRouter();
        $user = $request->getUser();
        $context = $request->getContext();

        $actionArgs = array_merge($this->getRequestArgs(), ['queryId' => $query->id]);

        // If appropriate, create an Edit action for the participants list
        if ($this->getAccessHelper()->getCanEdit($query->id)) {
            $editAction = new LinkAction(
                'editQuery',
                new AjaxModal(
                    $router->url($request, null, null, 'editQuery', null, $actionArgs),
                    __('grid.action.updateQuery'),
                ),
                __('grid.action.edit'),
                'edit'
            );
        } else {
            $editAction = null;
        }

        $leaveQueryLinkAction = new LinkAction(
            'leaveQuery',
            new RemoteActionConfirmationModal(
                $request->getSession(),
                __('submission.query.leaveQuery.confirm'),
                __('submission.query.leaveQuery'),
                $router->url($request, null, null, 'leaveQuery', null, $actionArgs),
                'negative'
            ),
            __('submission.query.leaveQuery'),
            'leaveQuery'
        );

        // Show leave query button for journal managers included in the query
        if ($user && $this->_getCurrentUserCanLeave($query->id)) {
            $showLeaveQueryButton = true;
        } else {
            $showLeaveQueryButton = false;
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'queryNotesGridHandlerName' => $this->getQueryNotesGridHandlerName(),
            'requestArgs' => $this->getRequestArgs(),
            'query' => $query,
            'editAction' => $editAction,
            'leaveQueryLinkAction' => $leaveQueryLinkAction,
            'showLeaveQueryButton' => $showLeaveQueryButton,
        ]);
        return new JSONMessage(true, $templateMgr->fetch('controllers/grid/queries/readQuery.tpl'));
    }

    /**
     * Fetch the list of participants for a query
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function participants($args, $request)
    {
        $query = $this->getQuery();
        $user = $request->getUser();

        $participants = [];
        $queryParticipants = $query->queryParticipants;
        foreach ($queryParticipants as $participant) {
            if ($participant->user) {
                $participants[] = $participant->user;
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('participants', $participants);

        if ($user && $this->_getCurrentUserCanLeave($query->id)) {
            $showLeaveQueryButton = true;
        } else {
            $showLeaveQueryButton = false;
        }
        $json = new JSONMessage();
        $json->setStatus(true);
        $json->setContent($templateMgr->fetch('controllers/grid/queries/participants.tpl'));
        $json->setAdditionalAttributes(['showLeaveQueryButton' => $showLeaveQueryButton]);
        return $json;
    }

    /**
     * Edit a query
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function editQuery($args, $request)
    {
        $query = $this->getQuery();
        if (!$this->getAccessHelper()->getCanEdit($query->id)) {
            return new JSONMessage(false);
        }

        // Form handling
        $queryForm = new QueryForm(
            $request,
            $this->getAssocType(),
            $this->getAssocId(),
            $this->getStageId(),
            $query->id
        );
        $queryForm->initData();
        return new JSONMessage(true, $queryForm->fetch($request, null, false, $this->getRequestArgs()));
    }

    /**
     * Save a query
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function updateQuery($args, $request)
    {
        $query = $this->getQuery();
        if (!$this->getAccessHelper()->getCanEdit($query->id)) {
            return new JSONMessage(false);
        }
        $oldParticipantIds = QueryParticipant::withQueryId($query->id)
            ->pluck('user_id')
            ->all();

        $queryForm = new QueryForm(
            $request,
            $this->getAssocType(),
            $this->getAssocId(),
            $this->getStageId(),
            $query->id
        );
        $queryForm->readInputData();

        if ($queryForm->validate()) {
            $queryForm->execute();
            $notificationMgr = new NotificationManager();

            if ($this->getStageId() == WORKFLOW_STAGE_ID_EDITING ||
                $this->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {
                // Update submission notifications
                $notificationMgr->updateNotification(
                    $request,
                    [
                        Notification::NOTIFICATION_TYPE_ASSIGN_COPYEDITOR,
                        Notification::NOTIFICATION_TYPE_AWAITING_COPYEDITS,
                        Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
                        Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS,
                    ],
                    null,
                    PKPApplication::ASSOC_TYPE_SUBMISSION,
                    $this->getAssocId()
                );
            }

            // Send notifications
            $currentUser = $request->getUser();
            $newParticipantIds = $queryForm->getData('users');
            $added = array_diff($newParticipantIds, $oldParticipantIds);
            // Don't notify the current user
            if ($key = array_search($currentUser->getId(), $added)) {
                unset($added[$key]);
            }

            /** @var NotificationSubscriptionSettingsDAO */
            $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
            $note = Repo::note()->getHeadNote($query->id);
            $submission = $this->getSubmission();

            // Find attachments if any
            $submissionFiles = Repo::submissionFile()
                ->getCollector()
                ->filterByAssoc(
                    PKPApplication::ASSOC_TYPE_NOTE,
                    [$note->id]
                )->filterBySubmissionIds([$submission->getId()])
                ->getMany();

            foreach ($added as $userId) {
                $user = Repo::user()->get((int) $userId);

                $notification = $notificationMgr->createNotification(
                    $request,
                    $userId,
                    Notification::NOTIFICATION_TYPE_NEW_QUERY,
                    $request->getContext()->getId(),
                    PKPApplication::ASSOC_TYPE_QUERY,
                    $query->id,
                    Notification::NOTIFICATION_LEVEL_TASK
                );

                // Check if the user is unsubscribed
                $notificationSubscriptionSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $user->getId(),
                    $request->getContext()->getId()
                );
                if (!$notification || in_array(Notification::NOTIFICATION_TYPE_NEW_QUERY, $notificationSubscriptionSettings)) {
                    continue;
                }

                $mailable = $this->getStageMailable($request->getContext(), $submission)
                    ->sender($currentUser)
                    ->recipients([$user])
                    ->subject($note->title)
                    ->body($note->contents)
                    ->allowUnsubscribe($notification);

                $submissionFiles->each(fn (SubmissionFile $item) => $mailable->attachSubmissionFile(
                    $item->getId(),
                    $item->getLocalizedData('name')
                ));

                Mail::send($mailable);
                Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::DISCUSSION_NOTIFY, $mailable, $submission);
            }

            return DAO::getDataChangedEvent($query->id);
        }

        // If this was new (placeholder) query that didn't validate, remember whether or not
        // we need to delete it on cancellation.
        if ($request->getUserVar('wasNew')) {
            $queryForm->setIsNew(true);
        }
        return new JSONMessage(
            true,
            $queryForm->fetch(
                $request,
                null,
                false,
                array_merge(
                    $this->getRequestArgs(),
                    ['queryId' => $query->id]
                )
            )
        );
    }

    /**
     * Leave query
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function leaveQuery($args, $request)
    {
        $queryId = $args['queryId'];
        $user = $request->getUser();
        if ($user && $this->_getCurrentUserCanLeave($queryId)) {
            QueryParticipant::withQueryId($queryId)
                ->withUserId($user->getId())
                ->delete();
            $json = new JSONMessage();
            $json->setEvent('user-left-discussion');
        } else {
            $json = new JSONMessage(false);
        }
        return $json;
    }

    /**
     * Check if the current user can leave a query. Only allow if query has more than two participants.
     *
     * @param int $queryId
     *
     * @return bool
     */
    public function _getCurrentUserCanLeave($queryId)
    {
        $userRoles = $this->getAuthorizedContextObject(PKPApplication::ASSOC_TYPE_USER_ROLES);
        if (!count(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, ], $userRoles))) {
            return false;
        }
        $participantIds = QueryParticipant::withQueryId($queryId)
            ->pluck('user_id')
            ->all();
        if (count($participantIds) < 3) {
            return false;
        }
        $user = Application::get()->getRequest()->getUser();
        return in_array($user->getId(), $participantIds);
    }

    /**
     * Fetches an email template's message body.
     *
     * @return JSONMessage JSON object
     */
    public function fetchTemplateBody(array $args, PKPRequest $request): JSONMessage
    {
        $templateId = $request->getUserVar('template');
        $context = $request->getContext();
        $template = Repo::emailTemplate()->getByKey($context->getId(), $templateId);
        if ($template) {
            $mailable = $this->getStageMailable($context, $this->getSubmission());
            $mailable->sender($request->getUser());
            $data = $mailable->getData();

            return new JSONMessage(
                true,
                [
                    'body' => Mail::compileParams($template->getLocalizedData('body'), $data),
                    'subject' => Mail::compileParams($template->getLocalizedData('subject'), $data),
                ]
            );
        }
    }
}
