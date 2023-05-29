<?php

/**
 * @file controllers/grid/users/reviewer/form/EditReviewForm.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditReviewForm
 *
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Allow the editor to limit the available files to an assigned
 * reviewer after the assignment has taken place.
 */

namespace PKP\controllers\grid\users\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\form\Form;
use PKP\mail\mailables\EditReviewNotify;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\notification\PKPNotification;
use PKP\reviewForm\ReviewFormDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewAssignment\ReviewAssignmentDAO;
use PKP\submission\ReviewFilesDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;

class EditReviewForm extends Form
{
    /** @var ReviewAssignment */
    public $_reviewAssignment;

    /** @var ReviewRound */
    public $_reviewRound;

    protected Submission $submission;

    public function __construct(ReviewAssignment $reviewAssignment, Submission $submission)
    {
        $this->_reviewAssignment = $reviewAssignment;
        $this->submission = $submission;
        assert($this->_reviewAssignment instanceof ReviewAssignment);

        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $this->_reviewRound = $reviewRoundDao->getById($reviewAssignment->getReviewRoundId());
        assert(is_a($this->_reviewRound, 'ReviewRound'));

        parent::__construct('controllers/grid/users/reviewer/form/editReviewForm.tpl');

        // Validation checks for this form
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'responseDueDate', 'required', 'editor.review.errorAddingReviewer'));
        $this->addCheck(new \PKP\form\validation\FormValidator($this, 'reviewDueDate', 'required', 'editor.review.errorAddingReviewer'));
        $this->addCheck(new \PKP\form\validation\FormValidatorPost($this));
        $this->addCheck(new \PKP\form\validation\FormValidatorCSRF($this));
    }

    //
    // Overridden template methods
    //
    /**
     * Initialize form data from the associated author.
     */
    public function initData()
    {
        $this->setData('responseDueDate', $this->_reviewAssignment->getDateResponseDue());
        $this->setData('reviewDueDate', $this->_reviewAssignment->getDateDue());
        return parent::initData();
    }

    /**
     * Fetch the Edit Review Form form
     *
     * @see Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $context = $request->getContext();

        if (!$this->_reviewAssignment->getDateCompleted()) {
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewFormsIterator = $reviewFormDao->getActiveByAssocId(Application::getContextAssocType(), $context->getId());
            $reviewForms = [];
            while ($reviewForm = $reviewFormsIterator->next()) {
                $reviewForms[$reviewForm->getId()] = $reviewForm->getLocalizedTitle();
            }
            $templateMgr->assign([
                'reviewForms' => $reviewForms,
                'reviewFormId' => $this->_reviewAssignment->getReviewFormId(),
            ]);
        }

        $templateMgr->assign([
            'stageId' => $this->_reviewAssignment->getStageId(),
            'reviewRoundId' => $this->_reviewRound->getId(),
            'submissionId' => $this->_reviewAssignment->getSubmissionId(),
            'reviewAssignmentId' => $this->_reviewAssignment->getId(),
            'reviewMethod' => $this->_reviewAssignment->getReviewMethod(),
            'reviewMethods' => $reviewAssignmentDao->getReviewMethodsTranslationKeys(),
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
            'selectedFiles',
            'responseDueDate',
            'reviewDueDate',
            'reviewMethod',
            'reviewFormId',

        ]);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        // Revoke all, then grant selected.
        $reviewFilesDao = DAORegistry::getDAO('ReviewFilesDAO'); /** @var ReviewFilesDAO $reviewFilesDao */
        $reviewFilesDao->revokeByReviewId($this->_reviewAssignment->getId());

        $fileStages = [$this->_reviewRound->getStageId() == WORKFLOW_STAGE_ID_INTERNAL_REVIEW ? SubmissionFile::SUBMISSION_FILE_INTERNAL_REVIEW_FILE : SubmissionFile::SUBMISSION_FILE_REVIEW_FILE];
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds([$this->_reviewAssignment->getSubmissionId()])
            ->filterByReviewRoundIds([$this->_reviewRound->getId()])
            ->filterByFileStages($fileStages)
            ->getMany();

        $selectedFiles = array_map(function ($id) {
            return (int) $id;
        }, (array) $this->getData('selectedFiles'));
        foreach ($submissionFiles as $submissionFile) {
            if (in_array($submissionFile->getId(), $selectedFiles)) {
                $reviewFilesDao->grant($this->_reviewAssignment->getId(), $submissionFile->getId());
            }
        }

        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignment = $reviewAssignmentDao->getReviewAssignment($this->_reviewRound->getId(), $this->_reviewAssignment->getReviewerId(), $this->_reviewRound->getRound(), $this->_reviewRound->getStageId());

        // Send notification to reviewer if details have changed.
        if (strtotime($reviewAssignment->getDateDue()) != strtotime($this->getData('reviewDueDate')) || strtotime($reviewAssignment->getDateResponseDue()) != strtotime($this->getData('responseDueDate')) || $reviewAssignment->getReviewMethod() != $this->getData('reviewMethod')) {
            $notificationManager = new NotificationManager();
            $request = Application::get()->getRequest();
            $context = $request->getContext();

            $notification = $notificationManager->createNotification(
                $request,
                $reviewAssignment->getReviewerId(),
                PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED,
                $context->getId(),
                PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $reviewAssignment->getId(),
                Notification::NOTIFICATION_LEVEL_TASK
            );

            // Check if user is subscribed to this type of notification emails
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            /** @var NotificationSubscriptionSettingsDAO */
            $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
            if ($notification && !in_array(
                PKPNotification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT_UPDATED,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $reviewer->getId(),
                    (int) $context->getId()
                )
            )
            ) {
                $template = Repo::emailTemplate()->getByKey($context->getId(), EditReviewNotify::getEmailTemplateKey());
                $mailable = new EditReviewNotify($context, $this->submission, $reviewAssignment);
                $mailable
                    ->sender($request->getUser())
                    ->recipients([$reviewer])
                    ->subject($template->getLocalizedData('subject'))
                    ->body($template->getLocalizedData('body'))
                    ->allowUnsubscribe($notification);

                Mail::send($mailable);
            }
        }

        $reviewAssignment->setDateDue($this->getData('reviewDueDate'));
        $reviewAssignment->setDateResponseDue($this->getData('responseDueDate'));
        $reviewAssignment->setReviewMethod($this->getData('reviewMethod'));

        if (!$reviewAssignment->getDateCompleted()) {
            // Ensure that the review form ID is valid, if specified
            $reviewFormId = (int) $this->getData('reviewFormId');
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewForm = $reviewFormDao->getById($reviewFormId, Application::getContextAssocType(), $context->getId());
            $reviewAssignment->setReviewFormId($reviewForm ? $reviewFormId : null);
        }

        $reviewAssignmentDao->updateObject($reviewAssignment);
        parent::execute(...$functionArgs);
    }
}
