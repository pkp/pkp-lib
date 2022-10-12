<?php

/**
 * @file controllers/grid/users/stageParticipant/form/PKPStageParticipantNotifyForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStageParticipantNotifyForm
 * @ingroup controllers_grid_users_stageParticipant_form
 *
 * @brief Form to notify a user regarding a file
 */

namespace PKP\controllers\grid\users\stageParticipant\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\controllers\grid\queries\traits\StageMailable;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\log\EventLogEntry;
use PKP\log\SubmissionEmailLogDAO;
use PKP\log\SubmissionEmailLogEntry;
use PKP\log\SubmissionLog;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use Symfony\Component\Mailer\Exception\TransportException;

use PKP\core\Core;

abstract class PKPStageParticipantNotifyForm extends Form
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

        if ($itemType == ASSOC_TYPE_SUBMISSION) {
            $this->_submissionId = $itemId;
        } else {
            $submissionFile = Repo::submissionFile()->get($itemId);
            $this->_submissionId = $submissionFile->getData('submissionId');
        }

        // Some other forms (e.g. the Add Participant form) subclass this form and
        // may not enforce the sending of an email.
        if ($this->isMessageRequired()) {
            $this->addCheck(new \PKP\form\validation\FormValidator($this, 'message', 'required', 'stageParticipants.notify.warning'));
        }
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'userId', 'required', 'stageParticipants.notify.warning'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $submission = Repo::submission()->get($this->_submissionId);

        // All stages can choose the default template
        $templateKeys = ['NOTIFICATION_CENTER_DEFAULT'];

        // Determine if the current user can use any custom templates defined.
        $user = $request->getUser();
        $customTemplateKeys = [];
        $roleDao = DAORegistry::getDAO('RoleDAO');
        if ($roleDao->userHasRole($submission->getData('contextId'), $user->getId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT])) {
            $emailTemplates = Repo::emailTemplate()->getCollector()
                ->filterByContext($submission->getData('contextId'))
                ->filterByIsCustom(true)
                ->getMany();

            foreach ($emailTemplates as $emailTemplate) {
                $customTemplateKeys[] = $emailTemplate->getData('key');
            }
        }

        $context = $request->getContext();
        $mailable = $this->getStageMailable($context, $submission);

        $currentStageId = $this->getStageId();
        $stageTemplates = $this->getStageTemplates();
        $templateKeys = array_merge($templateKeys, $stageTemplates ?? []);

        $templateKeyToSubject = function ($templateKey) use ($context, $submission, $mailable) {
            $template = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);
            $data = $mailable->getData(Locale::getLocale());
            return Mail::compileParams($template->getLocalizedData('subject'), $data);
        };

        $templates = array_combine($templateKeys, array_map($templateKeyToSubject, $templateKeys));
        if (count($customTemplateKeys)) {
            $templates[__('manager.emails.otherTemplates')] = array_combine($customTemplateKeys, array_map($templateKeyToSubject, $customTemplateKeys));
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'templates' => $templates,
            'stageId' => $currentStageId,
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
     *
     * @param int $userId the user id to send email to.
     * @param Submission $submission
     * @param PKPRequest $request
     */
    public function sendMessage($userId, $submission, $request)
    {
        $user = Repo::user()->get($userId);
        if (!isset($user)) {
            return;
        }

        $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */
        $context = $contextDao->getById($submission->getData('contextId'));
        $templateKey = $this->getData('template');
        $template = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);
        if (!$template) {
            $template = Repo::emailTemplate()->getByKey($context->getId(), 'NOTIFICATION');
        }

        $messageContent = $this->getData('message');
        // Set the empty subject; compile when template variables are set
        $mailable = $this->getStageMailable($context, $submission, '', $messageContent);

        $fromUser = $request->getUser();
        $mailable->sender($fromUser)
            ->recipients([$user]);

        $mailable->addData(['authorName' => $user->getFullName()]); // For compatibility with removed AUTHOR_ASSIGN and AUTHOR_NOTIFY
        $mailableData = $mailable->getData();
        $messageSubject = Mail::compileParams($template->getLocalizedData('subject'), $mailableData);
        $mailable->addData([
            $mailable::getSubjectVariableName() => $messageSubject,
        ]);

        // Include DISCUSSION_NOTIFICATION template rather than the message itself
        $notificationTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        $mailable->body($notificationTemplate->getLocalizedData('body'))
            ->subject($notificationTemplate->getLocalizedData('subject'));

        $logDao = null;
        try {
            Mail::send($mailable);
            $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $logDao */
        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            error_log($e->getMessage());
        }

        // remove the INDEX_ and LAYOUT_ tasks if a user has sent the appropriate _COMPLETE email
        switch ($templateKey) {
            case 'EDITOR_ASSIGN':
                $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGN, $user->getId(), $submission->getId());
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_EDITOR_ASSIGN, $mailable, $submission);
                break;
            case 'COPYEDIT_REQUEST':
                $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT, $user->getId(), $submission->getId());
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_COPYEDIT_NOTIFY_COPYEDITOR, $mailable, $submission);
                break;
            case 'LAYOUT_REQUEST':
                $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $user->getId(), $submission->getId());
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_LAYOUT_NOTIFY_EDITOR, $mailable, $submission);
                break;
            case 'INDEX_REQUEST':
                $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT, $user->getId(), $submission->getId());
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_INDEX_NOTIFY_INDEXER, $mailable, $submission);
                break;
            case 'LAYOUT_COMPLETE':
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_LAYOUT_NOTIFY_COMPLETE, $mailable, $submission);
                break;
            case 'INDEX_COMPLETE':
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_INDEX_NOTIFY_COMPLETE, $mailable, $submission);
                break;
            default:
                !$logDao ?: $logDao->logMailable(SubmissionEmailLogEntry::SUBMISSION_EMAIL_DISCUSSION_NOTIFY, $mailable, $submission);
                break;
        }

        // Create a query
        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $query = $queryDao->newDataObject();
        $query->setAssocType(PKPApplication::ASSOC_TYPE_SUBMISSION);
        $query->setAssocId($submission->getId());
        $query->setStageId($this->_stageId);
        $query->setSequence(REALLY_BIG_NUMBER);
        $queryDao->insertObject($query);
        $queryDao->resequence(PKPApplication::ASSOC_TYPE_SUBMISSION, $submission->getId());

        // Add the current user and message recipient as participants.
        $queryDao->insertParticipant($query->getId(), $user->getId());
        if ($user->getId() != $request->getUser()->getId()) {
            $queryDao->insertParticipant($query->getId(), $request->getUser()->getId());
        }

        // Create a head note
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        $headNote = $noteDao->newDataObject();
        $headNote->setUserId($request->getUser()->getId());
        $headNote->setAssocType(PKPApplication::ASSOC_TYPE_QUERY);
        $headNote->setAssocId($query->getId());
        $headNote->setDateCreated(Core::getCurrentDate());
        $headNote->setTitle($messageSubject);
        $headNote->setContents($messageContent);
        $noteDao->insertObject($headNote);

        if ($submission->getStageId() == WORKFLOW_STAGE_ID_EDITING ||
            $submission->getStageId() == WORKFLOW_STAGE_ID_PRODUCTION) {
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
                PKPApplication::ASSOC_TYPE_SUBMISSION,
                $submission->getId()
            );
        }
    }

    /**
     * Get the available email template variable names for the given template name.
     *
     * @param string $emailKey Email template key
     *
     * @return array
     */
    public function getEmailVariableNames($emailKey)
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
            case 'EDITOR_ASSIGN': return [
                'recipientName' => __('user.name'),
                'recipientUsername' => __('user.username'),
                'signature' => __('user.role.editor'),
                'submissionUrl' => __('common.url'),
            ];
        }
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
        $notificationDao = DAORegistry::getDAO('NotificationDAO'); /** @var NotificationDAO $notificationDao */
        $notificationFactory = $notificationDao->getByAssoc(
            ASSOC_TYPE_SUBMISSION,
            $submissionId,
            $userId,
            $type
        );

        if (!$notificationFactory->next()) {
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
        SubmissionLog::logEvent($request, $submission, EventLogEntry::SUBMISSION_LOG_MESSAGE_SENT, 'informationCenter.history.messageSent');

        // Create trivial notification.
        $currentUser = $request->getUser();
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('stageParticipants.history.messageSent')]);
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
