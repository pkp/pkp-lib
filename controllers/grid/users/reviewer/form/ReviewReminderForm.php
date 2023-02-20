<?php

/**
 * @file controllers/grid/users/reviewer/form/ReviewReminderForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewReminderForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending a review reminder to a reviewer
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\log\SubmissionEventLogEntry;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\log\SubmissionLog;
use PKP\mail\mailables\ReviewRemind;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\notification\PKPNotification;
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
        $user = $request->getUser();
        $context = $request->getContext();

        $reviewAssignment = $this->getReviewAssignment();
        $reviewerId = $reviewAssignment->getReviewerId();
        $reviewer = Repo::user()->get($reviewerId);

        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $mailable = new ReviewRemind($context, $submission, $reviewAssignment);
        $mailable->sender($user)->recipients([$reviewer]);
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        $data = $mailable->getData(Locale::getLocale());
        // Don't expose the reviewer's one-click access URL to editors
        $data[ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL] = '{$' . ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL . '}';
        $body = Mail::compileParams($template->getLocalizedData('body'), $data);

        $this->setData('stageId', $reviewAssignment->getStageId());
        $this->setData('reviewAssignmentId', $reviewAssignment->getId());
        $this->setData('submissionId', $submission->getId());
        $this->setData('reviewAssignment', $reviewAssignment);
        $this->setData('reviewerName', $reviewer->getFullName() . ' <' . $reviewer->getEmail() . '>');
        $this->setData('message', $body);
        $this->setData('reviewDueDate', $mailable->viewData[ReviewAssignmentEmailVariable::REVIEW_DUE_DATE]);
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
        $mailable = new ReviewRemind($context, $submission, $reviewAssignment);
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());
        $mailable
            ->subject($template->getLocalizedData('subject'))
            ->body($this->getData('message'))
            ->sender($user)
            ->recipients([$reviewer]);

        // Finally, send email and handle Symfony transport exceptions
        try {
            Mail::send($mailable);

            SubmissionLog::logEvent(
                $request,
                $submission,
                SubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REMIND,
                'submission.event.reviewer.reviewerReminded',
                [
                    'recipientId' => $reviewer->getId(),
                    'recipientName' => $reviewer->getFullName(),
                    'senderId' => $user->getId(),
                    'senderName' => $user->getFullName(),
                ]
            );

            $reviewAssignment->setDateReminded(Core::getCurrentDate());
            $reviewAssignment->stampModified();
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $reviewAssignmentDao->updateObject($reviewAssignment);

        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        parent::execute(...$functionArgs);
    }
}
