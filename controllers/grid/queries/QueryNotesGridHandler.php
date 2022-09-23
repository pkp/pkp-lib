<?php

/**
 * @file controllers/grid/queries/QueryNotesGridHandler.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridHandler
 * @ingroup controllers_grid_query
 *
 * @brief base PKP class to handle query grid requests.
 */

namespace PKP\controllers\grid\queries;

use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\queries\form\QueryNoteForm;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\note\NoteDAO;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\security\authorization\QueryAccessPolicy;
use PKP\security\Role;
use PKP\note\Note;
use APP\facades\Repo;
use APP\core\Application;
use APP\notification\Notification;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\queries\traits\StageMailable;
use APP\notification\NotificationManager;

class QueryNotesGridHandler extends GridHandler
{
    use StageMailable;

    /** @var User */
    public $_user;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['fetchGrid', 'fetchRow', 'addNote', 'insertNote', 'deleteNote']
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
        return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the query.
     *
     * @return Query
     */
    public function getQuery()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_QUERY);
    }

    /**
     * Get the stage id.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
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
        $stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy

        // Get the access policy
        $this->addPolicy(new QueryAccessPolicy($request, $args, $roleAssignments, $stageId));
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
        $this->setTitle('submission.query.messages');

        $cellProvider = new QueryNotesGridCellProvider($this->getSubmission());

        // Columns
        $this->addColumn(
            new GridColumn(
                'contents',
                'common.note',
                null,
                null,
                $cellProvider,
                ['width' => 80, 'html' => true]
            )
        );
        $this->addColumn(
            new GridColumn(
                'from',
                'submission.query.from',
                null,
                null,
                $cellProvider,
                ['html' => true]
            )
        );

        $this->_user = $request->getUser();
    }


    //
    // Overridden methods from GridHandler
    //
    /**
     * @copydoc GridHandler::getRowInstance()
     *
     * @return QueryNotesGridRow
     */
    public function getRowInstance()
    {
        return new QueryNotesGridRow($this->getRequestArgs(), $this->getQuery(), $this);
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
            'queryId' => $this->getQuery()->getId(),
        ];
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * @param null|mixed $filter
     */
    public function loadData($request, $filter = null)
    {
        return $this->getQuery()
            ->getReplies(null, NoteDAO::NOTE_ORDER_DATE_CREATED, \PKP\db\DAO::SORT_DIRECTION_ASC, $this->getCanManage(null));
    }

    //
    // Public Query Notes Grid Actions
    //
    /**
     * Present the form to add a new note.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function addNote($args, $request)
    {
        $queryNoteForm = new QueryNoteForm($this->getRequestArgs(), $this->getQuery(), $request->getUser());
        $queryNoteForm->initData();
        return new JSONMessage(true, $queryNoteForm->fetch($request));
    }

    /**
     * Insert a new note.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function insertNote($args, $request)
    {
        $queryNoteForm = new QueryNoteForm($this->getRequestArgs(), $this->getQuery(), $request->getUser(), $request->getUserVar('noteId'));
        $queryNoteForm->readInputData();
        if ($queryNoteForm->validate()) {
            $note = $queryNoteForm->execute();
            $this->insertedNoteNotify($note);
            return \PKP\db\DAO::getDataChangedEvent($this->getQuery()->getId());
        } else {
            return new JSONMessage(true, $queryNoteForm->fetch($request));
        }
    }

    /**
     * Determine whether the current user can manage (delete) a note.
     *
     * @param Note $note optional
     *
     * @return bool
     */
    public function getCanManage($note)
    {
        $isAdmin = (0 != count(array_intersect(
            $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES),
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_SUB_EDITOR]
        )));

        if ($note === null) {
            return $isAdmin;
        } else {
            return ($note->getUserId() == $this->_user->getId() || $isAdmin);
        }
    }

    /**
     * Delete a query note.
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function deleteNote($args, $request)
    {
        $query = $this->getQuery();
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $note = $noteDao->getById($request->getUserVar('noteId'));
        $user = $request->getUser();

        if (!$request->checkCSRF() || !$note || $note->getAssocType() != ASSOC_TYPE_QUERY || $note->getAssocId() != $query->getId()) {
            // The note didn't exist or has the wrong assoc info.
            return new JSONMessage(false);
        }

        if (!$this->getCanManage($note)) {
            // The user doesn't own the note and isn't priveleged enough to delete it.
            return new JSONMessage(false);
        }

        $noteDao->deleteObject($note);
        return \PKP\db\DAO::getDataChangedEvent($note->getId());
    }

    /**
     * Sends notification and email to the query participants
     */
    protected function insertedNoteNotify(Note $note): void
    {
        $notificationManager = new NotificationManager();
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->getById($note->getData('assocId'));
        $sender = Repo::user()->get($note->getData('userId'));
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $title = $query->getHeadNote()->getData('title');

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        foreach ($queryDao->getParticipantIds($query->getId()) as $userId) {
            // Delete any prior notifications of the same type (e.g. prior "new" comments)
            $notificationDao->deleteByAssoc(
                PKPApplication::ASSOC_TYPE_QUERY,
                $query->getId(),
                $userId,
                PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                $context->getId()
            );

            // No need to additionally notify the posting user.
            if ($userId == $sender->getId()) {
                continue;
            }

            // Notify the user of a new query.
            $notification = $notificationManager->createNotification(
                $request,
                $userId,
                PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                $request->getContext()->getId(),
                PKPApplication::ASSOC_TYPE_QUERY,
                $query->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Check if user is subscribed to this type of notification emails
            if (!$notification || in_array(PKPNotification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                    $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                        NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                        $userId,
                        (int) $context->getId()))
            ) {
                continue;
            }

            $mailable = $this->getStageMailable($context, $submission, $title, $note->getContents());
            $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

            $recipient = Repo::user()->get($userId);
            $mailable->addData(['notificationUrl'=>
                    Repo::submission()->getWorkflowUrlByUserRoles($submission, $userId)]
                    );
            $mailable->sender($sender)
                ->recipients([$recipient])
                ->subject(__('common.re').' '.$emailTemplate->getLocalizedData('subject'))
                ->body($emailTemplate->getLocalizedData('body'));

            Mail::send($mailable);
        }
    }
}