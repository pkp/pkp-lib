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
use PKP\controllers\grid\queries\traits\StageMailable;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\form\Form;
use PKP\form\validation\FormValidator;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\log\event\EventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\query\Query;
use PKP\query\QueryParticipant;
use PKP\security\Role;
use PKP\security\Validation;
use Symfony\Component\Mailer\Exception\TransportException;

class PKPStageParticipantNotifyForm extends Form
{
    use StageMailable;

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
        $submission = Repo::submission()->get($this->_submissionId);
        $context = $request->getContext();
        $user = $request->getUser();

        // Add the templates that can be used for this message
        if ($user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $context->getId())) {
            $mailable = $this->getStageMailable($context, $submission);
            $data = $mailable->getData();
            $defaultTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
            $templates = [$mailable::getEmailTemplateKey() => $defaultTemplate->getLocalizedData('name')];
            $alternateTemplates = Repo::emailTemplate()->getCollector($context->getId())
                ->alternateTo([$mailable::getEmailTemplateKey()])
                ->getMany();
            foreach ($alternateTemplates as $alternateTemplate) {
                $templates[$alternateTemplate->getData('key')] = Mail::compileParams(
                    $alternateTemplate->getLocalizedData('name'),
                    $data
                );
            }
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'templates' => $templates,
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
        $user = Repo::user()->get($userId);
        if (!isset($user)) {
            return;
        }

        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));
        $mailable = $this->getStageMailable($context, $submission);
        $templateKey = $this->getData('template');
        $template = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);
        if (!$template) {
            $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        }

        // Create a query
        $query = Query::create([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'stageId' => $this->_stageId,
            'seq' => REALLY_BIG_NUMBER
        ]);

        Repo::query()->resequence(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId());

        // Add the current user and message recipient as participants.
        QueryParticipant::create([
            'queryId' => $query->id,
            'userId' => $user->getId()
        ]);
        if ($user->getId() != $request->getUser()->getId()) {
            QueryParticipant::create([
                'queryId' => $query->id,
                'userId' => $request->getUser()->getId()
            ]);
        }

        // Populate mailable with data before compiling headNote
        $mailable
            ->addData(['authorName' => $user->getFullName()]) // For compatibility with removed AUTHOR_ASSIGN and AUTHOR_NOTIFY
            ->sender($request->getUser())
            ->recipients([$user])
            ->body($this->getData('message'))
            ->subject($template->getLocalizedData('subject'));

        //Substitute email template variables not available before form being executed
        $additionalVariables = $this->getEmailVariableNames($template->getData('key'));

        // Create a head note
        $headNote = Note::create([
            'userId' =>  $request->getUser()->getId(),
            'assocType' => PKPApplication::ASSOC_TYPE_QUERY,
            'assocId' => $query->id,
            'title' => Mail::compileParams(
                $template->getLocalizedData('subject'),
                $mailable->getData()
            ),
            'contents' => Mail::compileParams(
                $this->getData('message'),
                array_intersect_key($mailable->getData(), $additionalVariables)
            ),
        ]);

        // Send the email
        $notificationMgr = new NotificationManager();
        $notification = $notificationMgr->createNotification(
            $request,
            $userId,
            Notification::NOTIFICATION_TYPE_NEW_QUERY,
            $request->getContext()->getId(),
            PKPApplication::ASSOC_TYPE_QUERY,
            $query->id,
            Notification::NOTIFICATION_LEVEL_TASK
        );

        $mailable->allowUnsubscribe($notification);
        $logRepository = null;
        try {
            Mail::send($mailable);
            $logRepository = Repo::emailLogEntry();
        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            error_log($e->getMessage());
        }

        // remove the INDEX_ and LAYOUT_ tasks if a user has sent the appropriate _COMPLETE email
        switch ($templateKey) {
            case 'EDITOR_ASSIGN':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_EDITOR_ASSIGN, $user->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::EDITOR_ASSIGN, $mailable, $submission);
                break;
            case 'COPYEDIT_REQUEST':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT, $user->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::COPYEDIT_NOTIFY_COPYEDITOR, $mailable, $submission);
                break;
            case 'LAYOUT_REQUEST':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $user->getId(), $submission->getId());
                !$logRepository ?: $logRepository->logMailable(SubmissionEmailLogEventType::LAYOUT_NOTIFY_EDITOR, $mailable, $submission);
                break;
            case 'INDEX_REQUEST':
                $this->_addAssignmentTaskNotification($request, Notification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT, $user->getId(), $submission->getId());
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
    public function getEmailVariableNames(string $emailKey): array
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
                $request,
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
}
