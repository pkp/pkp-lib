<?php

/**
 * @file controllers/grid/queries/QueryNotesGridHandler.php
 *
 * Copyright (c) 2016-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryNotesGridHandler
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
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\grid\queries\form\QueryNoteForm;
use PKP\controllers\grid\queries\traits\StageMailable;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAO;
use PKP\db\DAORegistry;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\query\Query;
use PKP\query\QueryParticipant;
use PKP\security\authorization\QueryAccessPolicy;
use PKP\security\Role;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

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
     */
    public function getSubmission(): Submission
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_SUBMISSION);
    }

    /**
     * Get the query.
     *
     */
    public function getQuery(): ?Query
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_QUERY);
    }

    /**
     * Get the stage id.
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->getAuthorizedContextObject(Application::ASSOC_TYPE_WORKFLOW_STAGE);
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
            'queryId' => $this->getQuery()->id,
        ];
    }

    /**
     * @copydoc GridHandler::loadData()
     *
     * Incomplete notes are hidden from everyone except
     * the user who created them. These are considered
     * in-progress and not yet saved.
     *
     * @see https://github.com/pkp/pkp-lib/issues/1155
     *
     * @param null|mixed $filter
     */
    public function loadData($request, $filter = null)
    {
        return Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $this->getQuery()->id)
            ->withSort(Note::NOTE_ORDER_DATE_CREATED, DAO::SORT_DIRECTION_ASC)
            ->lazy()
            ->filter(function (Note $note) use ($request) {
                return $note->contents || $note->userId === $request->getUser()->getId();
            });
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
            return DAO::getDataChangedEvent($this->getQuery()->id);
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
            $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES),
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_SUB_EDITOR]
        )));

        return $note?->userId == $this->_user->getId() || $isAdmin;
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
        $note = Note::find((int) $request->getUserVar('noteId'));

        if (!$request->checkCSRF() || $note?->assocType != Application::ASSOC_TYPE_QUERY || $note?->assocId != $query->id) {
            // The note didn't exist or has the wrong assoc info.
            return new JSONMessage(false);
        }

        if (!$this->getCanManage($note)) {
            // The user doesn't own the note and isn't privileged enough to delete it.
            return new JSONMessage(false);
        }

        $note->delete();
        return DAO::getDataChangedEvent($note->id);
    }

    /**
     * Sends notification and email to the query participants
     */
    protected function insertedNoteNotify(Note $note): void
    {
        $notificationManager = new NotificationManager();
        $query = Query::find($note->assocId);
        $sender = $note->user;
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $submission = $this->getSubmission();
        $title = Repo::note()->getHeadNote($query->id)->title;

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        // Find attachments if any
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(
                PKPApplication::ASSOC_TYPE_NOTE,
                [$note->id]
            )->filterBySubmissionIds([$submission->getId()])
            ->getMany();
        $participantIds = QueryParticipant::withQueryId($query->id)
            ->pluck('user_id')
            ->all();
        foreach ($participantIds as $userId) {
            // Delete any prior notifications of the same type (e.g. prior "new" comments)
            Notification::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $query->id)
                ->withUserId($userId)
                ->withType(Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY)
                ->withContextId($context->getId())
                ->delete();

            // No need to additionally notify the posting user.
            if ($userId == $sender->getId()) {
                continue;
            }
            $recipient = Repo::user()->get($userId);
            // Do not attempt to notify disabled users. (pkp/pkp-lib#10402)
            if (!$recipient) {
                continue;
            }

            // Notify the user of a new query.
            $notification = $notificationManager->createNotification(
                $request,
                $userId,
                Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                $request->getContext()->getId(),
                PKPApplication::ASSOC_TYPE_QUERY,
                $query->id,
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Check if user is subscribed to this type of notification emails
            if (!$notification || in_array(
                Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $userId,
                    (int) $context->getId()
                )
            )) {
                continue;
            }

            $mailable = $this->getStageMailable($context, $submission)
                ->sender($sender)
                ->recipients([$recipient])
                ->subject(__('common.re') . ' ' . $title)
                ->body($note->contents)
                ->allowUnsubscribe($notification);

            $submissionFiles->each(fn (SubmissionFile $item) => $mailable->attachSubmissionFile(
                $item->getId(),
                $item->getLocalizedData('name')
            ));

            Mail::send($mailable);
        }
    }
}
