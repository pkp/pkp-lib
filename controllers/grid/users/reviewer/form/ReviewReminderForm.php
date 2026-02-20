<?php

/**
 * @file controllers/grid/users/reviewer/form/ReviewReminderForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminderForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending a review reminder to a reviewer
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\ReviewRemind;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\notification\Notification;
use PKP\security\Validation;
use PKP\submission\reviewAssignment\ReviewAssignment;
use Symfony\Component\Mailer\Exception\TransportException;

class ReviewReminderForm extends Form
{
    /** @var ReviewAssignment The review assignment associated with the reviewer */
    public $_reviewAssignment;

    /**
     * Constructor.
     */
    public function __construct($reviewAssignment)
    {
        parent::__construct('controllers/grid/users/reviewer/form/reviewReminderForm.tpl');
        $this->_reviewAssignment = $reviewAssignment;

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Getters and Setters
    //
    /**
     * Get the review assignment
     *
     * @return ReviewAssignment
     */
    public function getReviewAssignment()
    {
        return $this->_reviewAssignment;
    }


    //
    // Overridden template methods
    //
    /**
     * @copydoc Form::initData
     */
    public function initData()
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $reviewAssignment = $this->getReviewAssignment();
        $reviewerId = $reviewAssignment->getReviewerId();
        $reviewer = Repo::user()->get($reviewerId);

        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $mailable = $this->getReviewRemindMailable($context);
        $defaultTemplate = $this->getDefaultTemplate($context);
        $defaultTemplateKey = $defaultTemplate->getData('key');
        $data = $mailable->getData(Locale::getLocale());
        // Don't expose the reviewer's one-click access URL to editors
        $data[ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL] = '{$' . ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL . '}';
        $body = Mail::compileParams($defaultTemplate->getLocalizedData('body'), $data);

        $this->setData('stageId', $reviewAssignment->getStageId());
        $this->setData('reviewAssignmentId', $reviewAssignment->getId());
        $this->setData('submissionId', $submission->getId());
        $this->setData('reviewAssignment', $reviewAssignment);
        $this->setData('reviewerName', $reviewer->getFullName() . ' <' . $reviewer->getEmail() . '>');
        $this->setData('message', $body);
        $this->setData('reviewDueDate', $mailable->viewData[ReviewAssignmentEmailVariable::REVIEW_DUE_DATE]);
        // Default selected template in the UI
        $this->setData('defaultTemplateKey', $defaultTemplateKey);
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('emailVariables', [
            'passwordResetUrl' => __('common.url'),
        ]);
        // Provide templates for the <select>
        $templates = $this->getEmailTemplates();
        $defaultTemplateKey = array_key_first($templates);
        $templateMgr->assign([
            'templates' => $templates,
            'defaultTemplateKey' => $defaultTemplateKey,
        ]);
        return parent::fetch($request, $template, $display);
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'templateKey',
            'message',
            'reviewDueDate',
        ]);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $reviewAssignment = $this->getReviewAssignment();
        $reviewerId = $reviewAssignment->getReviewerId();
        $reviewer = Repo::user()->get($reviewerId);
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $user = $request->getUser();
        $context = $request->getContext();

        // Create ReviewRemind email and populate with data
        $mailable = $this->getReviewRemindMailable($context);
        $templateKey = $this->getData('templateKey') ?: $mailable::getEmailTemplateKey();
        $template = Repo::emailTemplate()->getByKey($context->getId(), $templateKey);
        if ($template === null) {
            throw new \Exception("Selected email template with key $templateKey not found.");
        }
        $mailable
            ->subject($template->getLocalizedData('subject'))
            ->body($this->getData('message'));

        // Finally, send email and handle Symfony transport exceptions
        try {
            Mail::send($mailable);

            $eventLog = Repo::eventLog()->newDataObject([
                'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
                'assocId' => $submission->getId(),
                'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND,
                'userId' => Validation::loggedInAs() ?? $user->getId(),
                'message' => 'submission.event.reviewer.reviewerReminded',
                'isTranslate' => 0,
                'dateLogged' => Core::getCurrentDate(),
                'recipientId' => $reviewer->getId(),
                'recipientName' => $reviewer->getFullName(),
                'senderId' => $user->getId(),
                'senderName' => $user->getFullName(),
            ]);
            Repo::eventLog()->add($eventLog);

            Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_REMIND, $mailable, $submission, $user);

            Repo::reviewAssignment()->edit($reviewAssignment, [
                'dateReminded' => Core::getCurrentDate(),
            ]);
        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        parent::execute(...$functionArgs);
    }

    /**
     * Get the mailable for this form, populated with necessary data for template variable replacement.
     *
     * @return ReviewRemind
     */
    public function getReviewRemindMailable($context)
    {
        $reviewAssignment = $this->getReviewAssignment();
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $mailable = new ReviewRemind($context, $submission, $reviewAssignment);
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
        $user = Application::get()->getRequest()->getUser();
        $mailable
            ->sender($user)
            ->recipients([$reviewer]);
        return $mailable;
    }

    public function getDefaultTemplate($context)
    {
        $mailable = $this->getReviewRemindMailable($context);
        $templateKey = $mailable::getEmailTemplateKey();
        $contextId = $context->getId();
        $defaultTemplate = Repo::emailTemplate()->getByKey($contextId, $templateKey);
        // Handle missing template case, showing an error message,
        // since the default template is required for the form to work properly.
        if ($defaultTemplate === null) {
            throw new \Exception("Default email template with key $templateKey not found for context " . $contextId);
        }
        return $defaultTemplate;
    }

    /**
     * Get available email templates for this form, with the default template as the first option.
     *
     * @return array
     */
    public function getEmailTemplates()
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $contextId = $context->getId();
        $mailable = $this->getReviewRemindMailable($context);
        $defaultTemplate = $this->getDefaultTemplate($context);
        $defaultTemplateKey = $defaultTemplate->getData('key');
        $data = $mailable->getData(Locale::getLocale());
        $templates = [$defaultTemplateKey => $defaultTemplate->getLocalizedData('name')];
        $alternateTemplates = Repo::emailTemplate()->getCollector($contextId)
            ->alternateTo([$defaultTemplateKey])
            ->getMany();
        foreach ($alternateTemplates as $alternateTemplate) {
            $templates[$alternateTemplate->getData('key')] =
                Mail::compileParams($alternateTemplate->getLocalizedData('name'), $data);
        }
        return $templates;
    }
}
