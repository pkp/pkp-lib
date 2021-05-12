<?php

/**
 * @file lib/pkp/controllers/grid/users/stageParticipant/form/PKPStageParticipantNotifyForm.inc.php
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

use APP\core\Services;
use APP\notification\Notification;
use APP\notification\NotificationManager;

use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\log\EventLogEntry;
use PKP\notification\PKPNotification;

abstract class PKPStageParticipantNotifyForm extends Form
{
    /** @var int The file/submission ID this form is for */
    public $_itemId;

    /** @var int The type of item the form is for (used to determine which email template to use) */
    public $_itemType;

    /** The stage Id **/
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
            $submissionFile = Services::get('submissionFile')->get($itemId);
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
        /** @var Submission $submissionDao */
        $submission = Services::get('submission')->get($this->_submissionId);

        // All stages can choose the default template
        $templateKeys = ['NOTIFICATION_CENTER_DEFAULT'];

        // Determine if the current user can use any custom templates defined.
        $user = $request->getUser();
        $customTemplateKeys = [];
        if (Services::get('user')->userHasRole($user->getId(), [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT], $submission->getData('contextId'))) {
            $emailTemplates = Services::get('emailTemplate')->getMany([
                'contextId' => $submission->getData('contextId'),
                'isCustom' => true,
            ]);
            foreach ($emailTemplates as $emailTemplate) {
                $customTemplateKeys[] = $emailTemplate->getData('key');
            }
        }

        $stageTemplates = $this->_getStageTemplates();
        $currentStageId = $this->getStageId();
        $templateKeys = array_merge($templateKeys, $stageTemplates[$currentStageId] ?? []);

        $templateKeyToSubject = function ($templateKey) use ($submission) {
            $mailTemplate = $this->_getMailTemplate($submission, $templateKey);
            $mailTemplate->assignParams([]);
            $mailTemplate->replaceParams();
            return $mailTemplate->getSubject();
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
            $user = Services::get('user')->get($request->getUserVar('userId'));
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
        $submissionDao = DAORegistry::getDAO('SubmissionDAO'); /** @var SubmissionDAO $submissionDao */
        $submission = $submissionDao->getById($this->_submissionId);
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
     * @param $userId int the user id to send email to.
     * @param $submission Submission
     * @param $request PKPRequest
     */
    public function sendMessage($userId, $submission, $request)
    {
        $template = $this->getData('template');
        $fromUser = $request->getUser();

        $email = $this->_getMailTemplate($submission, $template, false);
        $email->setReplyTo($fromUser->getEmail(), $fromUser->getFullName());

        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $user = $userDao->getById($userId);
        if (isset($user)) {
            $email->addRecipient($user->getEmail(), $user->getFullName());
            $email->setBody($this->getData('message'));

            $submissionUrl = Services::get('submission')->getWorkflowUrlByUserRoles($submission, $user->getId());

            // Parameters for various emails
            $email->assignParams([
                // COPYEDIT_REQUEST, LAYOUT_REQUEST, INDEX_REQUEST
                'participantName' => $user->getFullName(),
                'participantUsername' => $user->getUsername(),
                'submissionUrl' => $submissionUrl,
                // LAYOUT_COMPLETE, INDEX_COMPLETE, EDITOR_ASSIGN
                'editorialContactName' => $user->getFullname(),
                // EDITOR_ASSIGN
                'editorUsername' => $user->getUsername(),
                // AUTHOR ASSIGN, AUTHOR NOTIFY
                'authorName' => $user->getFullName(),
            ]);

            $suppressNotificationEmail = false;

            if (!$email->send($request)) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
            } else {
                $suppressNotificationEmail = true;
            }

            // remove the INDEX_ and LAYOUT_ tasks if a user has sent the appropriate _COMPLETE email
            switch ($template) {
                case 'EDITOR_ASSIGN':
                    $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGN, $user->getId(), $submission->getId(), $suppressNotificationEmail);
                    break;
                case 'COPYEDIT_REQUEST':
                    $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_COPYEDIT_ASSIGNMENT, $user->getId(), $submission->getId(), $suppressNotificationEmail);
                    break;
                case 'LAYOUT_REQUEST':
                    $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_LAYOUT_ASSIGNMENT, $user->getId(), $submission->getId(), $suppressNotificationEmail);
                    break;
                case 'INDEX_REQUEST':
                    $this->_addAssignmentTaskNotification($request, PKPNotification::NOTIFICATION_TYPE_INDEX_ASSIGNMENT, $user->getId(), $submission->getId(), $suppressNotificationEmail);
                    break;
            }

            // Create a query
            $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
            $query = $queryDao->newDataObject();
            $query->setAssocType(ASSOC_TYPE_SUBMISSION);
            $query->setAssocId($submission->getId());
            $query->setStageId($this->_stageId);
            $query->setSequence(REALLY_BIG_NUMBER);
            $queryDao->insertObject($query);
            $queryDao->resequence(ASSOC_TYPE_SUBMISSION, $submission->getId());

            // Add the current user and message recipient as participants.
            $queryDao->insertParticipant($query->getId(), $user->getId());
            if ($user->getId() != $request->getUser()->getId()) {
                $queryDao->insertParticipant($query->getId(), $request->getUser()->getId());
            }

            // Create a head note
            $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
            $headNote = $noteDao->newDataObject();
            $headNote->setUserId($request->getUser()->getId());
            $headNote->setAssocType(ASSOC_TYPE_QUERY);
            $headNote->setAssocId($query->getId());
            $headNote->setDateCreated(Core::getCurrentDate());
            $headNote->setTitle($email->getSubject());
            $headNote->setContents($email->getBody());
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
                    ASSOC_TYPE_SUBMISSION,
                    $submission->getId()
                );
            }
        }
    }

    /**
     * Get the available email template variable names for the given template name.
     *
     * @param $emailKey string Email template key
     *
     * @return array
     */
    public function getEmailVariableNames($emailKey)
    {
        switch ($emailKey) {
            case 'COPYEDIT_REQUEST':
            case 'LAYOUT_REQUEST':
            case 'INDEX_REQUEST': return [
                'participantName' => __('user.name'),
                'participantUsername' => __('user.username'),
                'submissionUrl' => __('common.url'),
            ];
            case 'LAYOUT_COMPLETE':
            case 'INDEX_COMPLETE': return [
                'editorialContactName' => __('user.role.editor'),
            ];
            case 'EDITOR_ASSIGN': return [
                'editorUsername' => __('user.username'),
                'editorialContactName' => __('user.role.editor'),
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
     * @param $request PKPRequest
     * @param $type int NOTIFICATION_TYPE_...
     * @param $userId int User ID
     * @param $submissionId int Submission ID
     * @param $suppressEmail bool Indicates whether not to send the Notification email to the user.
     */
    private function _addAssignmentTaskNotification($request, $type, $userId, $submissionId, $suppressEmail = false)
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
                ASSOC_TYPE_SUBMISSION,
                $submissionId,
                Notification::NOTIFICATION_LEVEL_TASK,
                null,
                $suppressEmail
            );
        }
    }

    /**
     * Convenience function for logging the message sent event and creating the notification.
     *
     * @param $request PKPRequest
     * @param $submission Submission
     */
    public function _logEventAndCreateNotification($request, $submission)
    {
        import('lib.pkp.classes.log.SubmissionLog');
        SubmissionLog::logEvent($request, $submission, EventLogEntry::SUBMISSION_LOG_MESSAGE_SENT, 'informationCenter.history.messageSent');

        // Create trivial notification.
        $currentUser = $request->getUser();
        $notificationMgr = new NotificationManager();
        $notificationMgr->createTrivialNotification($currentUser->getId(), PKPNotification::NOTIFICATION_TYPE_SUCCESS, ['contents' => __('stageParticipants.history.messageSent')]);
    }

    /**
     * whether or not to include the Notify Users listbuilder  true, by default.
     *
     * @return boolean
     */
    public function isMessageRequired()
    {
        return true;
    }

    /**
     * return app-specific stage templates.
     *
     * @return array
     */
    abstract protected function _getStageTemplates();

    /**
     * Return app-specific mail template.
     *
     * @param $submission Submission
     * @param $templateKey string
     * @param $includeSignature boolean
     *
     * @return SubmissionMailTemplate
     */
    abstract protected function _getMailTemplate($submission, $templateKey, $includeSignature = true);
}
