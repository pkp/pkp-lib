<?php

/**
 * @file controllers/grid/users/stageParticipant/form/PKPStageParticipantNotifyForm.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStageParticipantNotifyForm
 *
 * @ingroup controllers_grid_users_stageParticipant_form
 *
 * @brief Form to notify a user regarding a file
 */

namespace PKP\controllers\grid\users\stageParticipant\form;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\context\Context;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\editorialTask\EditorialTask;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\editorialTask\Participant;
use PKP\editorialTask\Template;
use PKP\editorialTask\TemplateVariables;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\log\event\EventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\Mailable;
use PKP\mail\traits\Discussion;
use PKP\mail\traits\Recipient;
use PKP\mail\traits\Sender;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\userGroup\UserGroup;
use Symfony\Component\Mailer\Exception\TransportException;

class PKPStageParticipantNotifyForm extends Form
{
    /** @var int The file/submission ID this form is for */
    public $_itemId;

    /** @var int The type of item the form is for (used to determine which email template to use) */
    public $_itemType;

    /** @var int The stage Id */
    public $_stageId;

    /** @var int the Submission id */
    public $_submissionId;

    /**
     * Constructor.
     *
     * @param null|mixed $template
     */
    public function __construct($itemId, $itemType, $stageId, $template = null)
    {
        $template = ($template != null) ? $template : 'controllers/grid/users/stageParticipant/form/notify.tpl';
        parent::__construct($template);
        $this->_itemId = $itemId;
        $this->_itemType = $itemType;
        $this->_stageId = $stageId;

        if ($itemType == Application::ASSOC_TYPE_SUBMISSION) {
            $this->_submissionId = $itemId;
        } else {
            $submissionFile = Repo::submissionFile()->get($itemId);
            $this->_submissionId = $submissionFile->getData('submissionId');
        }

        // Some other forms (e.g. the Add Participant form) subclass this form and
        // may not enforce the sending of an email.
        if ($this->isMessageRequired()) {
            $this->addCheck(new FormValidator($this, 'message', 'required', 'stageParticipants.notify.warning'));
        }
        $this->addCheck(new FormValidator($this, 'userId', 'required', 'stageParticipants.notify.warning'));
        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $context = $request->getContext();
        $user = $request->getUser();
        $templateData = [];

        // Add the templates that can be used for this message
        if ($user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $context->getId())) {
            $userGroupIds = UserGroup::withUserIds([$user->getId()])
                ->withContextIds([$context->getId()])
                ->pluck((new UserGroup())->getPrimaryKeyName())
                ->toArray();

            $collector = Template::withContextId($context->getId())
                ->withStageId($this->_stageId)
                ->withType(EditorialTaskType::DISCUSSION->value);

            $user->hasRole([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $context->getId()) ?
                $templates = $collector->get() :
                $templates = $collector->withUserGroupsAccess($userGroupIds)->get();

            $templateData = $templates->mapWithKeys(
                fn (Template $template, int $key) =>
                [$template->id => $template->getLocalizedData('title')]
            )->toArray();
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'templates' => $templateData,
            'stageId' => $this->getStageId(),
            'submissionId' => $this->_submissionId,
            'itemId' => $this->_itemId,
        ]);

        if ($request->getUserVar('userId')) {
            $user = Repo::user()->get($request->getUserVar('userId'));
            if ($user) {
                $templateMgr->assign([
                    'userId' => $user->getId(),
                    'userFullName' => $user->getFullName(),
                ]);
            }
        }

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['message', 'userId', 'template']);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionParams)
    {
        $submission = Repo::submission()->get($this->_submissionId);
        if ($this->getData('message')) {
            $request = Application::get()->getRequest();
            $this->sendMessage((int) $this->getData('userId'), $submission, $request);
            $this->_logEventAndCreateNotification($request, $submission);
        }
        return parent::execute(...$functionParams);
    }

    /**
     * Send a message to a user.
     */
    public function sendMessage(int $userId, Submission $submission, Request $request)
    {
        $recipient = Repo::user()->get($userId);
        if (!isset($recipient)) {
            return;
        }
        $sender = $request->getUser();
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));
        $templateId = $this->getData('template');

        $template = null;

        if ($templateId) {
            $template = Template::withContextId($context->getId())->find($templateId);
        }

        // Template is used to create a mailable, title for the message, identify the type of the notification. Fallback to the default one if it's not accessible
        if (!is_a($template, Template::class) || !Repo::editorialTask()->isTemplateAccessibleToUser($template, $sender)) {
            $template = Template::withKeys(Repo::editorialTask()->getDiscussionTemplateKeys(), $context->getId())
                ->withStageId($this->_stageId)
                ->withType(EditorialTaskType::DISCUSSION->value)
                ->first();
        }

        // If no template exists, use a default mailable.
        $mailable = $template ? new TemplateVariables($template->promote($submission), $submission, $context) : new class($submission, $context) extends Mailable
        {
            use Sender;
            use Recipient;
            use Discussion;

            public function __construct(protected Submission $submission, protected Context $context)
            {
                parent::__construct(func_get_args());
            }
        };

        // Populate mailable with data before compiling headNote
        $mailable
            ->addData(['authorName' => $recipient->getFullName()]) // For compatibility with removed AUTHOR_ASSIGN and AUTHOR_NOTIFY
            ->sender($sender)
            ->recipients([$recipient])
            ->body($this->getData('message'))
            ->subject($this->getDiscussionTitle($template));

        // Create a query
        $query = EditorialTask::create([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'stageId' => $this->_stageId,
            'seq' => REALLY_BIG_NUMBER,
            'createdBy' => $sender->getId(),
            'type' => EditorialTaskType::DISCUSSION,
            'title' => $this->getDiscussionTitle($template),
        ]);

        Repo::editorialTask()->resequence(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId());

        // Add the current user and message recipient as participants.
        Participant::create([
            'editTaskId' => $query->id,
            'userId' => $recipient->getId()
        ]);
        if ($recipient->getId() != $request->getUser()->getId()) {
            Participant::create([
                'editTaskId' => $query->id,
                'userId' => $request->getUser()->getId()
            ]);
        }

        $additionalVariables = [];
        if ($template) {
            $templateKey = $template->key;
            $additionalVariables = $this->getEmailVariableNames($templateKey);
        }

        // Create a head note
        Note::create([
            'userId' => $sender->getId(),
            'assocType' => PKPApplication::ASSOC_TYPE_QUERY,
            'assocId' => $query->id,
            'contents' => Mail::compileParams(
                $this->getData('message'),
                array_intersect_key($mailable->getData(), $additionalVariables)
            ),
        ]);

        // Send the email
        $notificationMgr = new NotificationManager();
        $notification = $notificationMgr->createNotification(
            $recipient->getId(),
            Notification::NOTIFICATION_TYPE_NEW_QUERY,
            $request->getContext()->getId(),
            PKPApplication::ASSOC_TYPE_QUERY,
            $query->id,
            Notification::NOTIFICATION_LEVEL_TASK
        );

        $logRepository = null;
        if ($notification) {
            // Only send the email if notifications have not been disabled
            $mailable->allowUnsubscribe($notification);
            try {
                Mail::send($mailable);
                $logRepository = Repo::emailLogEntry();
            } catch (TransportException $e) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification(
                    $sender->getId(),
                    Notification::NOTIFICATION_TYPE_ERROR,
                    ['contents' => __('email.compose.error')]
                );
                error_log($e->getMessage());
            }
        }

        // remove the INDEX_ and LAYOUT_ tasks if a user has sent the appropriate _COMPLETE email

        switch ($templateKey ?? '') {
            case 'EDITOR_ASSIGN':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_EDITOR_ASSIGN, $recipient->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::EDITOR_ASSIGN, $mailable, $submission);
                break;
            case 'COPYEDIT_REQUEST':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT, $recipient->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::COPYEDIT_NOTIFY_COPYEDITOR, $mailable, $submission);
                break;
            case 'LAYOUT_REQUEST':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $recipient->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::LAYOUT_NOTIFY_EDITOR, $mailable, $submission);
                break;
            case 'INDEX_REQUEST':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT, $recipient->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::INDEX_NOTIFY_INDEXER, $mailable, $submission);
                break;
            case 'LAYOUT_COMPLETE':
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::LAYOUT_NOTIFY_COMPLETE, $mailable, $submission);
                break;
            case 'INDEX_COMPLETE':
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::INDEX_NOTIFY_COMPLETE, $mailable, $submission);
                break;
            default:
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::DISCUSSION_NOTIFY, $mailable, $submission);
                break;
        }

        if ($submission->getData('stageId') == WORKFLOW_STAGE_ID_EDITING ||
            $submission->getData('stageId') == WORKFLOW_STAGE_ID_PRODUCTION) {
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
                $submission->getId()
            );
        }
    }

    /**
     * Get the available email template variable names for the given template name.
     */
    public function getEmailVariableNames(?string $emailKey): array
    {
        switch ($emailKey) {
            case 'COPYEDIT_REQUEST':
            case 'LAYOUT_REQUEST':
            case 'INDEX_REQUEST': return [
                'recipientName' => __('user.name'),
                'recipientUsername' => __('user.username'),
                'submissionUrl' => __('common.url'),
            ];
            case 'LAYOUT_COMPLETE':
            case 'INDEX_COMPLETE': return [
                'recipientName' => __('user.role.editor'),
            ];
            case 'EDITOR_ASSIGN_SUBMISSION':
            case 'EDITOR_ASSIGN_REVIEW':
            case 'EDITOR_ASSIGN_PRODUCTION':
            case 'EDITOR_ASSIGN': return [
                'recipientName' => __('user.name'),
                'recipientUsername' => __('user.username'),
                'signature' => __('user.role.editor'),
                'submissionUrl' => __('common.url'),
            ];
        }
        return [];
    }

    /**
     * Get the stage ID
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->_stageId;
    }

    /**
     * Add upload task notifications.
     *
     * @param PKPRequest $request
     * @param int $type NOTIFICATION_TYPE_...
     * @param int $userId User ID
     * @param int $submissionId Submission ID
     */
    private function _addAssignmentTaskNotification($request, $type, $userId, $submissionId)
    {
        $notification = Notification::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->withUserId($userId)
            ->withType($type)
            ->first();

        if (!$notification) {
            $context = $request->getContext();
            $notificationMgr = new NotificationManager();
            $notificationMgr->createNotification(
                $userId,
                $type,
                $context->getId(),
                PKPApplication::ASSOC_TYPE_SUBMISSION,
                $submissionId,
                Notification::NOTIFICATION_LEVEL_TASK
            );
        }
    }

    /**
     * Convenience function for logging the message sent event and creating the notification.
     *
     * @param PKPRequest $request
     * @param Submission $submission
     */
    public function _logEventAndCreateNotification($request, $submission)
    {
        $currentUser = $request->getUser();
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => EventLogEntry::SUBMISSION_LOG_MESSAGE_SENT,
            'userId' => Validation::loggedInAs() ?? $currentUser->getId(),
            'impersonatedUserId' => Validation::loggedInAs() ? $currentUser->getId() : null,
            'message' => 'informationCenter.history.messageSent',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate()
        ]);
        Repo::eventLog()->add($eventLog);

        // Create trivial notification.
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification($currentUser->getId(), Notification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('stageParticipants.history.messageSent')]);
    }

    /**
     * whether or not to include the Notify Users listbuilder  true, by default.
     *
     * @return bool
     */
    public function isMessageRequired()
    {
        return true;
    }

    /**
     * Get the discussion title for the current stage.
     * It's used if no template is available.
     *
     * @return string
     */
    protected function getDiscussionTitle(?Template $template = null): string
    {
        if (!is_null($template)) {
            return $template->getLocalizedData('title');
        }

        return Repo::editorialTask()->getDiscussionTitles()->get($this->_stageId, '');
    }
}
