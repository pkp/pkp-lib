<?php

/**
 * @file controllers/grid/users/reviewer/form/ThankReviewerForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThankReviewerForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending a thank you to a reviewer
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\log\SubmissionEmailLogDAO;
use PKP\log\SubmissionEmailLogEntry;
use PKP\mail\mailables\ReviewAcknowledgement;
use PKP\notification\PKPNotification;
use PKP\plugins\Hook;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use Symfony\Component\Mailer\Exception\TransportException;

class ThankReviewerForm extends Form
{
    /** @var ReviewAssignment The review assignment associated with the reviewer */
    public $_reviewAssignment;

    /**
     * Constructor.
     */
    public function __construct($reviewAssignment)
    {
        parent::__construct('controllers/grid/users/reviewer/form/thankReviewerForm.tpl');
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
        $reviewAssignment = $this->getReviewAssignment();
        $reviewerId = $reviewAssignment->getReviewerId();
        $reviewer = Repo::user()->get($reviewerId);
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));

        $mailable = new ReviewAcknowledgement($context, $submission, $reviewAssignment);
        $mailable->sender($user)->recipients([$reviewer]);
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable->getEmailTemplateKey());

        $this->setData('submissionId', $submission->getId());
        $this->setData('stageId', $reviewAssignment->getStageId());
        $this->setData('reviewAssignmentId', $reviewAssignment->getId());
        $this->setData('reviewAssignment', $reviewAssignment);
        $this->setData('reviewerName', $reviewer->getFullName() . ' <' . $reviewer->getEmail() . '>');
        $this->setData('message', Mail::compileParams($template->getLocalizedData('body'), $mailable->getData(Locale::getLocale())));
    }

    /**
     * Assign form data to user-submitted data.
     *
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars(['message', 'skipEmail']);
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
        $contextDao = Application::getContextDAO();
        $context = $contextDao->getById($submission->getData('contextId'));
        $user = $request->getUser();

        // Create mailable and populate with data
        $mailable = new ReviewAcknowledgement($context, $submission, $reviewAssignment);
        $mailable->sender($user)->recipients([$reviewer]);
        $template = Repo::emailTemplate()->getByKey($context->getId(), $mailable->getEmailTemplateKey());
        $mailable->body($this->getData('message'))->subject($template->getLocalizedData('subject'));

        Hook::call('ThankReviewerForm::thankReviewer', [$submission, $reviewAssignment, $mailable]);
        if (!$this->getData('skipEmail')) {
            $mailable->setData(Locale::getLocale());
            try {
                Mail::send($mailable);
                $submissionEmailLogDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $submissionEmailLogDao */
                $submissionEmailLogDao->logMailable(
                    SubmissionEmailLogEntry::SUBMISSION_EMAIL_REVIEW_THANK_REVIEWER,
                    $mailable,
                    $submission,
                    $user,
                );
            } catch (TransportException $e) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification(
                    $request->getUser()->getId(),
                    PKPNotification::NOTIFICATION_TYPE_ERROR,
                    ['contents' => __('email.compose.error')]
                );
                trigger_error($e->getMessage(), E_USER_WARNING);
            }
        }

        // update the ReviewAssignment with the acknowledged date
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment->setDateAcknowledged(Core::getCurrentDate());
        $reviewAssignment->stampModified();
        if (!in_array($reviewAssignment->getConsidered(), [ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED, ReviewAssignment::REVIEW_ASSIGNMENT_RECONSIDERED])) {
            $reviewAssignment->setConsidered(
                $reviewAssignment->getConsidered() === ReviewAssignment::REVIEW_ASSIGNMENT_NEW
                    ? ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED
                    : ReviewAssignment::REVIEW_ASSIGNMENT_RECONSIDERED
            );
        }
        $reviewAssignmentDao->updateObject($reviewAssignment);

        parent::execute(...$functionArgs);
    }
}
