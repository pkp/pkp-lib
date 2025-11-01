<?php

/**
 * @file controllers/grid/users/reviewer/form/EmailReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending an email to a user
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\form\Form;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\Mailable;
use PKP\notification\Notification;
use PKP\submission\reviewAssignment\ReviewAssignment;
use Symfony\Component\Mailer\Exception\TransportException;

class EmailReviewerForm extends Form
{
    /** @var ReviewAssignment The review assignment to use for this contact */
    public $_reviewAssignment;

    protected Submission $submission;

    /**
     * Constructor.
     *
     * @param ReviewAssignment $reviewAssignment The review assignment to use for this contact.
     * @param Submission $submission
     */
    public function __construct($reviewAssignment, $submission)
    {
        parent::__construct('controllers/grid/users/reviewer/form/emailReviewerForm.tpl');

        $this->_reviewAssignment = $reviewAssignment;
        $this->submission = $submission;

        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'subject', 'required', 'email.subjectRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'message', 'required', 'email.bodyRequired'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'subject',
            'message',
        ]);
    }

    /**
     * Display the form.
     *
     * @param array $requestArgs Request parameters to bounce back with the form submission.
     * @param null|mixed $template
     *
     * @see Form::fetch
     */
    public function fetch($request, $template = null, $display = false, $requestArgs = [])
    {
        $templateMgr = TemplateManager::getManager($request);
        $invitation = null;
        if(!$this->_reviewAssignment->getReviewerId()){
            $invitation = Repo::invitation()->getInvitationReviewerAssignmentId($this->_reviewAssignment->getId());
        }
        $templateMgr->assign([
            'userFullName' => $this->_reviewAssignment->getReviewerFullName() ? $this->_reviewAssignment->getReviewerFullName() : $invitation->getEmail(),
            'requestArgs' => $requestArgs,
            'reviewAssignmentId' => $this->_reviewAssignment->getId(),
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * Send the email
     */
    public function execute(...$functionArgs)
    {
        $invitation = null;
        $toUser = null;
        if(!$this->_reviewAssignment->getReviewerId()){
            $invitation = Repo::invitation()->getInvitationReviewerAssignmentId($this->_reviewAssignment->getId());
        } else {
            $toUser = Repo::user()->get($this->_reviewAssignment->getReviewerId());
        }
        $request = Application::get()->getRequest();
        $fromUser = $request->getUser();

        $mailable = new Mailable([$request->getContext(), $this->submission]);
        $mailable->to($toUser?$toUser->getEmail():$invitation->getEmail(), $toUser?->getFullName());
        $mailable->from($fromUser->getEmail(), $fromUser->getFullName());
        $mailable->replyTo($fromUser->getEmail(), $fromUser->getFullName());
        $mailable->subject($this->getData('subject'));
        $mailable->body($this->getData('message'));

        try {
            Mail::send($mailable);
            Repo::emailLogEntry()->logMailable(
                SubmissionEmailLogEventType::REVIEW_NOTIFY_REVIEWER,
                $mailable,
                $this->submission,
                $fromUser,
            );
        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $fromUser->getId(),
                Notification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        parent::execute(...$functionArgs);
    }
}
