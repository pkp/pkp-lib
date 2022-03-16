<?php

/**
 * @file controllers/grid/users/reviewer/form/EmailReviewerForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending an email to a user
 */

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;
use PKP\form\Form;
use PKP\mail\SubmissionMailTemplate;
use APP\submission\Submission;
use PKP\notification\PKPNotification;
use PKP\submission\reviewAssignment\ReviewAssignment;

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
        $user = Repo::user()->get($this->_reviewAssignment->getReviewerId());

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'userFullName' => $this->_reviewAssignment->getReviewerFullName(),
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
        $toUser = Repo::user()->get($this->_reviewAssignment->getReviewerId());
        $request = Application::get()->getRequest();
        $fromUser = $request->getUser();

        $email = new SubmissionMailTemplate($this->submission);

        $email->addRecipient($toUser->getEmail(), $toUser->getFullName());
        $email->setReplyTo($fromUser->getEmail(), $fromUser->getFullName());
        $email->setSubject($this->getData('subject'));
        $email->setBody($this->getData('message'));
        $email->assignParams();
        if (!$email->send()) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
        }

        parent::execute(...$functionArgs);
    }
}
