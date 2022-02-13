<?php

/**
 * @file controllers/grid/users/reviewer/form/ReviewReminderForm.inc.php
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

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\template\TemplateManager;

use PKP\form\Form;
use PKP\mail\Mailable;
use PKP\mail\mailables\ReviewRemind;
use PKP\mail\mailables\ReviewRemindOneclick;
use PKP\mail\variables\ReviewAssignmentEmailVariable;
use PKP\notification\PKPNotification;
use PKP\security\AccessKeyManager;
use PKP\submission\PKPSubmission;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\i18n\PKPLocale;
use PKP\security\Validation;
use Illuminate\Support\Facades\Mail;
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
        $mailable = $this->getMailable($context, $submission, $reviewAssignment);
        $mailable->sender($user)->recipients([$reviewer]);
        $template = $mailable->getTemplate($context->getId());
        $body = Mail::compileParams($template->getLocalizedData('body'), $mailable->getData(PKPLocale::getLocale()));

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
        $dispatcher = $request->getDispatcher();
        $user = $request->getUser();
        $context = $request->getContext();

        // Create ReviewRemind/ReviewRemindOneclick email and populate with data
        $mailable = $this->getMailable($context, $submission, $reviewAssignment);
        $mailable->sender($user)->recipients([$reviewer]);
        $template = $mailable->getTemplate($context->getId());
        $mailable->subject($template->getLocalizedData('subject'))->body($this->getData('message'));
        $mailable->setData(PKPLocale::getLocale());

        // Override reviewAssignmentUrl template variable if one-click reviewer access is enabled
        if ($context->getData('reviewerAccessKeysEnabled')) {
            $accessKeyManager = new AccessKeyManager();
            $expiryDays = ($context->getData('numWeeksPerReview') + 4) * 7;
            $accessKey = $accessKeyManager->createKey($context->getId(), $reviewerId, $reviewAssignment->getId(), $expiryDays);
            $reviewUrlArgs = [
                'submissionId' => $reviewAssignment->getSubmissionId(),
                'reviewId' => $reviewAssignment->getId(),
                'key' => $accessKey,
            ];
            $mailable->addData([
                ReviewAssignmentEmailVariable::REVIEW_ASSIGNMENT_URL =>
                    $dispatcher->url(
                        $request,
                        PKPApplication::ROUTE_PAGE,
                        null,
                        'reviewer',
                        'submission',
                        null,
                        $reviewUrlArgs
                    ),
            ]);
        }

        // Old Review Remind templates contain additional variable not supplied by _Variable classes
        $mailable->addData([
            'passwordResetUrl' =>
                $dispatcher->url(
                    $request,
                    PKPApplication::ROUTE_PAGE,
                    null,
                    'login',
                    'resetPassword',
                    $reviewer->getUsername(),
                    ['confirm' => Validation::generatePasswordResetHash($reviewer->getId())]),
        ]);

        // Finally, send email and handle Symfony transport exceptions
        try {
            Mail::send($mailable);
        } catch(TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            trigger_error($e->getMessage(), E_USER_WARNING);
        }

        // Update the ReviewAssignment with the reminded and modified dates
        $reviewAssignment->setDateReminded(Core::getCurrentDate());
        $reviewAssignment->stampModified();
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignmentDao->updateObject($reviewAssignment);

        parent::execute(...$functionArgs);
    }

    /**
     * Get the Mailable depending on if reviewer one click access is
     * enabled or not.
     *
     * @return ReviewRemind|ReviewRemindOneclick
     */
    protected function getMailable(
        Context $context,
        PKPSubmission $submission,
        ReviewAssignment $reviewAssignment
    ): Mailable
    {
        return $context->getData('reviewerAccessKeysEnabled') ?
            new ReviewRemindOneclick($context, $submission, $reviewAssignment) :
            new ReviewRemind($context, $submission, $reviewAssignment);
    }
}
