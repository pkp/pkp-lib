<?php

/**
 * @file classes/submission/reviewer/form/PKPReviewerReviewStep3Form.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPReviewerReviewStep3Form
 *
 * @ingroup submission_reviewer_form
 *
 * @brief Form for Step 3 of a review.
 */

namespace PKP\submission\reviewer\form;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\Mail;
use PKP\plugins\Hook;
use PKP\controllers\confirmationModal\linkAction\ViewReviewGuidelinesLinkAction;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\form\validation\FormValidatorCustom;
use PKP\form\validation\FormValidatorCSRF;
use PKP\form\validation\FormValidatorPost;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\log\SubmissionEmailLogEventType;
use PKP\mail\mailables\ReviewCompleteNotifyEditors;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\reviewForm\ReviewFormDAO;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponse;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;

class PKPReviewerReviewStep3Form extends ReviewerReviewForm
{
    /**
     * Constructor.
     */
    public function __construct(PKPRequest $request, Submission $reviewSubmission, ReviewAssignment $reviewAssignment)
    {
        parent::__construct($request, $reviewSubmission, $reviewAssignment, 3);

        // Validation checks for this form
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
        $requiredReviewFormElementIds = $reviewFormElementDao->getRequiredReviewFormElementIds($reviewAssignment->getReviewFormId());
        $this->addCheck(new FormValidatorCustom($this, 'reviewFormResponses', 'required', 'reviewer.submission.reviewFormResponse.form.responseRequired', function ($reviewFormResponses) use ($requiredReviewFormElementIds) {
            foreach ($requiredReviewFormElementIds as $requiredReviewFormElementId) {
                if (!isset($reviewFormResponses[$requiredReviewFormElementId]) || $reviewFormResponses[$requiredReviewFormElementId] == '') {
                    return false;
                }
            }
            return true;
        }));

        $this->addCheck(new FormValidatorPost($this));
        $this->addCheck(new FormValidatorCSRF($this));
    }

    /**
     * @copydoc ReviewerReviewForm::initData
     */
    public function initData()
    {
        $reviewAssignment = $this->getReviewAssignment();

        // Retrieve most recent reviewer comments, one private, one public.
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */

        $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), $reviewAssignment->getReviewerId(), $reviewAssignment->getId(), true);
        $submissionComment = $submissionComments->next(); /** @var \PKP\submission\SubmissionComment $submissionComment */
        $this->setData('comments', $submissionComment ? $submissionComment->getComments() : '');

        $submissionCommentsPrivate = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), $reviewAssignment->getReviewerId(), $reviewAssignment->getId(), false);
        $submissionCommentPrivate = $submissionCommentsPrivate->next(); /** @var \PKP\submission\SubmissionComment $submissionCommentPrivate */
        $this->setData('commentsPrivate', $submissionCommentPrivate ? $submissionCommentPrivate->getComments() : '');

        parent::initData();
    }

    //
    // Implement protected template methods from Form
    //
    /**
     * @see Form::readInputData()
     */
    public function readInputData()
    {
        $this->readUserVars([
            'reviewFormResponses',
            'comments',
            'reviewerRecommendationId',
            'commentsPrivate'
        ]);
    }

    /**
     * @copydoc ReviewerReviewForm::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $templateMgr = TemplateManager::getManager($request);
        $reviewAssignment = $this->getReviewAssignment();

        // Assign the objects and data to the template.
        $context = $this->request->getContext();
        $templateMgr->assign([
            'reviewAssignment' => $reviewAssignment,
            'reviewRoundId' => $reviewAssignment->getReviewRoundId(),
            'reviewerRecommendationOptions' => Repo::reviewerRecommendation()->getRecommendationOptions(
                context: $context,
                reviewAssignment: $reviewAssignment
            ),
        ]);

        if ($reviewAssignment->getReviewFormId()) {
            // Get the review form components
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $templateMgr->assign([
                'reviewForm' => $reviewFormDao->getById($reviewAssignment->getReviewFormId(), Application::getContextAssocType(), $context->getId()),
                'reviewFormElements' => $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId()),
                'reviewFormResponses' => $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId()),
                'disabled' => isset($reviewAssignment) && $reviewAssignment->getDateCompleted() != null,
            ]);
        }

        //
        // Assign the link actions
        //
        $viewReviewGuidelinesAction = new ViewReviewGuidelinesLinkAction($request, $reviewAssignment->getStageId());
        if ($viewReviewGuidelinesAction->getGuidelines()) {
            $templateMgr->assign('viewGuidelinesAction', $viewReviewGuidelinesAction);
        }

        return parent::fetch($request, $template, $display);
    }

    /**
     * @see Form::execute()
     */
    public function execute(...$functionParams)
    {
        $reviewAssignment = $this->getReviewAssignment();
        $notificationMgr = new NotificationManager();

        // Save the answers to the review form
        $this->saveReviewForm($reviewAssignment);

        // Send notification
        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());
        $context = Application::getContextDAO()->getById($submission->getData('contextId'));

        // Set review to next step.
        $this->updateReviewStepAndSaveSubmission($this->getReviewAssignment());

        // Persist the updated review assignment.
        Repo::reviewAssignment()->edit($reviewAssignment, [
            'dateCompleted' => Core::getCurrentDate(), // Mark the review assignment as completed.
            'reviewerRecommendationId' => $this->getData('reviewerRecommendationId'), // assign the recommendation to the review assignment, if there was one.
        ]);

        $reviewAssignment = Repo::reviewAssignment()->get($reviewAssignment->getId());

        // Retrieve stage assignments for managers and sub-editors
        $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$submission->getData('stageId')])
            ->whereHas('userGroup', function ($query) {
                $query->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);
            })
            ->get();

        $receivedList = [];
        // get the NotificationSubscriptionSettingsDAO
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO'); /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */

        foreach ($stageAssignments as $stageAssignment) {
            $userId = $stageAssignment->userId;
            $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId);
            // Never send reviewer comment notification to users other than managers and editors.
            if (!in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]) || in_array($userId, $receivedList)) {
                continue;
            }
            // Notify editors
            $notification = $notificationMgr->createNotification(
                $userId,
                Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT,
                $submission->getData('contextId'),
                PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $reviewAssignment->getId()
            );

            // Check if user is subscribed to this type of notification emails
            if (!$notification || in_array(
                Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT,
                $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                    NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                    $userId,
                    (int) $context->getId()
                )
            )
            ) {
                continue;
            }

            $mailable = new ReviewCompleteNotifyEditors($context, $submission, $reviewAssignment);
            $template = Repo::emailTemplate()->getByKey($context->getId(), ReviewCompleteNotifyEditors::getEmailTemplateKey());

            if (!$template) {
                $template = Repo::emailTemplate()->getByKey($context->getId(), 'NOTIFICATION');
                $request = Application::get()->getRequest();
                $mailable->addData([
                    'notificationContents' => $notificationMgr->getNotificationContents($request, $notification),
                    'notificationUrl' => $notificationMgr->getNotificationUrl($request, $notification),
                ]);
            }

            $user = Repo::user()->get($userId);
            $mailable
                ->from($context->getData('contactEmail'), $context->getData('contactName'))
                ->recipients([$user])
                ->subject($template->getLocalizedData('subject'))
                ->body($template->getLocalizedData('body'))
                ->allowUnsubscribe($notification); // include unsubscribe link

            Mail::send($mailable);
            Repo::emailLogEntry()->logMailable(SubmissionEmailLogEventType::REVIEW_COMPLETE, $mailable, $submission, $user);

            $receivedList[] = $userId;
        }

        // Remove the task
        Notification::withAssoc(PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT, $reviewAssignment->getId())
            ->withUserId($reviewAssignment->getReviewerId())
            ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
            ->delete();

        // Add log
        $reviewer = Repo::user()->get($reviewAssignment->getReviewerId(), true);
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submission->getId(),
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_READY,
            'userId' => Validation::loggedInAs() ?? Application::get()->getRequest()->getUser()->getId(),
            'message' => 'log.review.reviewReady',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'reviewAssignmentId' => $reviewAssignment->getId(),
            'reviewerName' => $reviewer->getFullName(),
            'submissionId' => $reviewAssignment->getSubmissionId(),
            'round' => $reviewAssignment->getRound()
        ]);
        Repo::eventLog()->add($eventLog);

        parent::execute(...$functionParams);
    }

    /**
     * Save the given answers for later
     */
    public function saveForLater()
    {
        $reviewAssignment = $this->getReviewAssignment();

        // Save the answers to the review form
        $this->saveReviewForm($reviewAssignment);

        // Persist the updated review assignment.
        Repo::reviewAssignment()->edit($reviewAssignment, [
            // save the recommendation to the review assignment
            'reviewerRecommendationId' => (int)$this->getData('reviewerRecommendationId') === 0
                ? null
                : (int)$this->getData('reviewerRecommendationId'),
        ]);

        Hook::call(strtolower(get_class($this)) . '::saveForLater', [$this]);
        return true;
    }

    /**
     * Save the given answers to the review form
     *
     * @param ReviewAssignment $reviewAssignment
     */
    public function saveReviewForm($reviewAssignment)
    {
        if ($reviewAssignment->getReviewFormId()) {
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
            $reviewFormResponses = $this->getData('reviewFormResponses');
            if (is_array($reviewFormResponses)) {
                foreach ($reviewFormResponses as $reviewFormElementId => $reviewFormResponseValue) {
                    $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewAssignment->getId(), $reviewFormElementId);
                    if (!isset($reviewFormResponse)) {
                        $reviewFormResponse = new ReviewFormResponse();
                    }
                    $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
                    $reviewFormElement = $reviewFormElementDao->getById($reviewFormElementId);
                    $elementType = $reviewFormElement->getElementType();
                    switch ($elementType) {
                        case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD:
                        case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD:
                        case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA:
                            $reviewFormResponse->setResponseType('string');
                            $reviewFormResponse->setValue($reviewFormResponseValue);
                            break;
                        case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS:
                        case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX:
                            $reviewFormResponse->setResponseType('int');
                            $reviewFormResponse->setValue($reviewFormResponseValue);
                            break;
                        case ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES:
                            $reviewFormResponse->setResponseType('object');
                            $reviewFormResponse->setValue($reviewFormResponseValue);
                            break;
                    }
                    if ($reviewFormResponse->getReviewFormElementId() != null && $reviewFormResponse->getReviewId() != null) {
                        $reviewFormResponseDao->updateObject($reviewFormResponse);
                    } else {
                        $reviewFormResponse->setReviewFormElementId($reviewFormElementId);
                        $reviewFormResponse->setReviewId($reviewAssignment->getId());
                        $reviewFormResponseDao->insertObject($reviewFormResponse);
                    }
                }
            }
        } else {
            // No review form configured. Use the default form.
            if (strlen($comments = $this->getData('comments')) > 0) {
                // Create a comment with the review.
                $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
                $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), $reviewAssignment->getReviewerId(), $reviewAssignment->getId(), true);
                $comment = $submissionComments->next(); /** @var \PKP\submission\SubmissionComment $comment */

                if (!isset($comment)) {
                    $comment = $submissionCommentDao->newDataObject();
                }

                $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
                $comment->setRoleId(Role::ROLE_ID_REVIEWER);
                $comment->setAssocId($reviewAssignment->getId());
                $comment->setSubmissionId($reviewAssignment->getSubmissionId());
                $comment->setAuthorId($reviewAssignment->getReviewerId());
                $comment->setComments($comments);
                $comment->setCommentTitle('');
                $comment->setViewable(true);
                $comment->setDatePosted(Core::getCurrentDate());

                // Save or update
                if ($comment->getId() != null) {
                    $submissionCommentDao->updateObject($comment);
                } else {
                    $submissionCommentDao->insertObject($comment);
                }
            }
            unset($comment);

            if (strlen($commentsPrivate = $this->getData('commentsPrivate')) > 0) {
                // Create a comment with the review.
                $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
                $submissionCommentsPrivate = $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), $reviewAssignment->getReviewerId(), $reviewAssignment->getId(), false);
                $comment = $submissionCommentsPrivate->next(); /** @var \PKP\submission\SubmissionComment $comment */

                if (!isset($comment)) {
                    $comment = $submissionCommentDao->newDataObject();
                }

                $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
                $comment->setRoleId(Role::ROLE_ID_REVIEWER);
                $comment->setAssocId($reviewAssignment->getId());
                $comment->setSubmissionId($reviewAssignment->getSubmissionId());
                $comment->setAuthorId($reviewAssignment->getReviewerId());
                $comment->setComments($commentsPrivate);
                $comment->setCommentTitle('');
                $comment->setViewable(false);
                $comment->setDatePosted(Core::getCurrentDate());

                // Save or update
                if ($comment->getId() != null) {
                    $submissionCommentDao->updateObject($comment);
                } else {
                    $submissionCommentDao->insertObject($comment);
                }
            }
            unset($comment);
        }
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\reviewer\form\PKPReviewerReviewStep3Form', '\PKPReviewerReviewStep3Form');
}
