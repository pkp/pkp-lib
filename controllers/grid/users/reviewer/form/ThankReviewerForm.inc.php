<?php

/**
 * @file controllers/grid/users/reviewer/form/ThankReviewerForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ThankReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for sending a thank you to a reviewer
 */

use APP\facades\Repo;
use APP\notification\NotificationManager;
use PKP\form\Form;
use PKP\mail\SubmissionMailTemplate;

use PKP\notification\PKPNotification;

class ThankReviewerForm extends Form
{
    /** The review assignment associated with the reviewer **/
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
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */
        $user = $request->getUser();
        $context = $request->getContext();

        $reviewAssignment = $this->getReviewAssignment();
        $reviewerId = $reviewAssignment->getReviewerId();
        $reviewer = $userDao->getById($reviewerId);

        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        $email = new SubmissionMailTemplate($submission, 'REVIEW_ACK');

        $dispatcher = $request->getDispatcher();
        $email->assignParams([
            'reviewerName' => $reviewer->getFullName(),
            'reviewerUserName' => $reviewer->getUsername(),
            'passwordResetUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'login', 'resetPassword', $reviewer->getUsername(), ['confirm' => Validation::generatePasswordResetHash($reviewer->getId())]),
            'submissionReviewUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, null, 'reviewer', 'submission', null, ['submissionId' => $reviewAssignment->getSubmissionId()])
        ]);
        $email->replaceParams();

        $this->setData('submissionId', $submission->getId());
        $this->setData('stageId', $reviewAssignment->getStageId());
        $this->setData('reviewAssignmentId', $reviewAssignment->getId());
        $this->setData('reviewAssignment', $reviewAssignment);
        $this->setData('reviewerName', $reviewer->getFullName() . ' <' . $reviewer->getEmail() . '>');
        $this->setData('message', $email->getBody());
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
        $userDao = DAORegistry::getDAO('UserDAO'); /** @var UserDAO $userDao */

        $reviewAssignment = $this->getReviewAssignment();
        $reviewerId = $reviewAssignment->getReviewerId();
        $reviewer = $userDao->getById($reviewerId);
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        $email = new SubmissionMailTemplate($submission, 'REVIEW_ACK', null, null, null, false);

        $email->addRecipient($reviewer->getEmail(), $reviewer->getFullName());
        $email->setBody($this->getData('message'));

        if (!$this->getData('skipEmail')) {
            HookRegistry::call('ThankReviewerForm::thankReviewer', [&$submission, &$reviewAssignment, &$email]);
            $request = Application::get()->getRequest();
            $dispatcher = $request->getDispatcher();
            $context = $request->getContext();
            $user = $request->getUser();
            $email->assignParams([
                'reviewerName' => $reviewer->getFullName(),
                'contextUrl' => $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath()),
                'editorialContactSignature' => $user->getContactSignature(),
                'signatureFullName' => $user->getFullname(),
            ]);
            if (!$email->send($request)) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification($request->getUser()->getId(), PKPNotification::NOTIFICATION_TYPE_ERROR, ['contents' => __('email.compose.error')]);
            }
        }

        // update the ReviewAssignment with the acknowledged date
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment->setDateAcknowledged(Core::getCurrentDate());
        $reviewAssignment->stampModified();
        $reviewAssignment->setUnconsidered(REVIEW_ASSIGNMENT_NOT_UNCONSIDERED);
        $reviewAssignmentDao->updateObject($reviewAssignment);

        parent::execute(...$functionArgs);
    }
}
