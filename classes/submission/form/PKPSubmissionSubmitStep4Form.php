<?php

/**
 * @file classes/submission/form/PKPSubmissionSubmitStep4Form.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionSubmitStep4Form
 * @ingroup submission_form
 *
 * @brief Form for Step 4 of author submission: confirm & complete
 */

namespace PKP\submission\form;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use APP\log\SubmissionEventLogEntry;
use APP\notification\Notification;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\core\Core;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\log\SubmissionEmailLogDAO;
use PKP\log\SubmissionEmailLogEntry;
use PKP\log\SubmissionLog;
use PKP\mail\mailables\SubmissionAcknowledgement;
use PKP\mail\mailables\SubmissionAcknowledgementNotAuthor;
use PKP\notification\PKPNotification;
use PKP\security\Role;
use PKP\security\UserGroupDAO;
use PKP\user\User;
use Symfony\Component\Mailer\Exception\TransportException;

class PKPSubmissionSubmitStep4Form extends SubmissionSubmitForm
{
    /**
     * Array of Users that are going to be used for email notifications
     */
    protected array $emailRecipients = [];

    /**
     * Constructor.
     *
     * @param Context $context
     * @param Submission $submission
     */
    public function __construct($context, $submission)
    {
        parent::__construct($context, $submission, 4);
    }

    /**
     * Save changes to submission.
     *
     * @return int the submission ID
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();

        // Set other submission data.
        if ($this->submission->getSubmissionProgress() <= $this->step) {
            $this->submission->setDateSubmitted(Core::getCurrentDate());
            $this->submission->stampLastActivity();
            $this->submission->stampModified();
            $this->submission->setSubmissionProgress(0);
        }

        parent::execute(...$functionArgs);

        // Save the submission.
        Repo::submission()->dao->update($this->submission);

        // Assign the default stage participants.
        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $notifyUsers = [];

        // Manager and assistant roles -- for each assigned to this
        //  stage in setup, iff there is only one user for the group,
        //  automatically assign the user to the stage.
        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $submissionStageGroups = $userGroupDao->getUserGroupsByStage($this->submission->getContextId(), WORKFLOW_STAGE_ID_SUBMISSION);
        while ($userGroup = $submissionStageGroups->next()) {
            // Only handle manager and assistant roles
            if (!in_array($userGroup->getRoleId(), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_ASSISTANT])) {
                continue;
            }

            $users = $userGroupDao->getUsersById($userGroup->getId(), $this->submission->getContextId());
            if ($users->getCount() == 1) {
                $user = $users->next();
                $stageAssignmentDao->build($this->submission->getId(), $userGroup->getId(), $user->getId(), $userGroup->getRecommendOnly());
                $notifyUsers[] = $user->getId();
            }
        }

        // Author roles
        // Assign only the submitter in whatever ROLE_ID_AUTHOR capacity they were assigned previously
        $user = $request->getUser();
        $submitterAssignments = $stageAssignmentDao->getBySubmissionAndStageId($this->submission->getId(), null, null, $user->getId());
        while ($assignment = $submitterAssignments->next()) {
            $userGroup = $userGroupDao->getById($assignment->getUserGroupId());
            if ($userGroup->getRoleId() == Role::ROLE_ID_AUTHOR) {
                $stageAssignmentDao->build($this->submission->getId(), $userGroup->getId(), $assignment->getUserId());
                // Only assign them once, since otherwise we'll one assignment for each previous stage.
                // And as long as they are assigned once, they will get access to their submission.
                break;
            }
        }

        $notificationManager = new NotificationManager();

        // Assign sub editors for sections
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $subEditors = $subEditorsDao->getBySubmissionGroupId($this->submission->getSectionId(), ASSOC_TYPE_SECTION, $this->submission->getContextId());
        foreach ($subEditors as $subEditor) {
            $userGroups = $userGroupDao->getByUserId($subEditor->getId(), $this->submission->getContextId());
            while ($userGroup = $userGroups->next()) {
                if ($userGroup->getRoleId() != Role::ROLE_ID_SUB_EDITOR) {
                    continue;
                }
                $stageAssignmentDao->build($this->submission->getId(), $userGroup->getId(), $subEditor->getId(), $userGroup->getRecommendOnly());
                // If we assign a stage assignment in the Submission stage to a sub editor, make note.
                if ($userGroupDao->userGroupAssignedToStage($userGroup->getId(), WORKFLOW_STAGE_ID_SUBMISSION)) {
                    $notifyUsers[] = $subEditor->getId();
                }
            }
        }

        // Assign sub editors for categories
        $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var SubEditorsDAO $subEditorsDao */
        $categories = Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByPublicationIds([$this->submission->getCurrentPublication()->getId()])
        );
        foreach ($categories as $category) {
            $subEditors = $subEditorsDao->getBySubmissionGroupId($category->getId(), ASSOC_TYPE_CATEGORY, $this->submission->getContextId());
            foreach ($subEditors as $subEditor) {
                $userGroups = $userGroupDao->getByUserId($subEditor->getId(), $this->submission->getContextId());
                while ($userGroup = $userGroups->next()) {
                    if ($userGroup->getRoleId() != Role::ROLE_ID_SUB_EDITOR) {
                        continue;
                    }
                    $stageAssignmentDao->build($this->submission->getId(), $userGroup->getId(), $subEditor->getId(), $userGroup->getRecommendOnly());
                    // If we assign a stage assignment in the Submission stage to a sub editor, make note.
                    if ($userGroupDao->userGroupAssignedToStage($userGroup->getId(), WORKFLOW_STAGE_ID_SUBMISSION)) {
                        $notifyUsers[] = $subEditor->getId();
                    }
                }
            }
        }

        // Update assignment notifications
        $notificationManager->updateNotification(
            $request,
            $notificationManager->getDecisionStageNotifications(),
            null,
            ASSOC_TYPE_SUBMISSION,
            $this->submission->getId()
        );

        // Send a notification to associated users if an editor needs assigning
        if (empty($notifyUsers)) {
            $collector = Repo::user()->getCollector()
                ->filterByRoleIds([Role::ROLE_ID_MANAGER])
                ->filterByContextIds([$this->submission->getContextId()]);
            $managerIds = Repo::user()->getIds($collector);
            foreach ($managerIds as $userId) {
                // Add TASK notification indicating that a submission is unassigned
                $notificationManager->createNotification(
                    $request,
                    $userId,
                    PKPNotification::NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_REQUIRED,
                    $this->submission->getContextId(),
                    PKPApplication::ASSOC_TYPE_SUBMISSION,
                    $this->submission->getId(),
                    Notification::NOTIFICATION_LEVEL_TASK
                );
            }
        } else {
            foreach ($notifyUsers as $userId) {
                $notificationManager->createNotification(
                    $request,
                    $userId,
                    PKPNotification::NOTIFICATION_TYPE_SUBMISSION_SUBMITTED,
                    $this->submission->getContextId(),
                    ASSOC_TYPE_SUBMISSION,
                    $this->submission->getId()
                );
            }
        }

        $notificationManager->updateNotification(
            $request,
            [PKPNotification::NOTIFICATION_TYPE_APPROVE_SUBMISSION],
            null,
            ASSOC_TYPE_SUBMISSION,
            $this->submission->getId()
        );

        /**
         * Define email recipients
         */
        $user = $request->getUser();

        $assignedAuthors = Repo::author()->getSubmissionAuthors($this->submission);

        if ($assignedAuthors->count() > 0) {
            foreach ($assignedAuthors as $author) {
                $authorEmail = $author->getEmail();
                // only add the author email if they have not aalready been added
                // as the user creating the submission.
                if ($authorEmail != $user->getEmail()) {
                    array_push($this->emailRecipients, $author);
                }
            }
        }

        $this->sendEmail();

        return $this->submissionId;
    }

    /**
     * Sends email to the submission's authors
     */
    protected function sendEmail(): void
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        $logDao = DAORegistry::getDAO('SubmissionEmailLogDAO'); /** @var SubmissionEmailLogDAO $logDao */

        $submissionAck = $this->setSubmissionAckMailable($context, $user);

        try {
            Mail::send($submissionAck);
            $logDao->logMailable(
                SubmissionEmailLogEntry::SUBMISSION_EMAIL_AUTHOR_SUBMISSION_ACK,
                $submissionAck,
                $this->submission
            );
        } catch (TransportException $e) {
            $notificationMgr = new NotificationManager();
            $notificationMgr->createTrivialNotification(
                $request->getUser()->getId(),
                PKPNotification::NOTIFICATION_TYPE_ERROR,
                ['contents' => __('email.compose.error')]
            );
            error_log($e->getMessage());
        }

        $submissionAckNotAuthor = $this->setSubmissionAckNotAuthorMailable($context, $user);

        foreach ($this->emailRecipients as $authorEmailRecipient) {
            $submissionAckNotAuthor->recipients([$authorEmailRecipient]);

            try {
                Mail::send($submissionAckNotAuthor);
                $logDao->logMailable(
                    SubmissionEmailLogEntry::SUBMISSION_EMAIL_AUTHOR_SUBMISSION_ACK,
                    $submissionAckNotAuthor,
                    $this->submission
                );
            } catch (TransportException $e) {
                $notificationMgr = new NotificationManager();
                $notificationMgr->createTrivialNotification(
                    $request->getUser()->getId(),
                    PKPNotification::NOTIFICATION_TYPE_ERROR,
                    ['contents' => __('email.compose.error')]
                );
                error_log($e->getMessage());
            }
        }

        // Log submission.
        SubmissionLog::logEvent(
            $request,
            $this->submission,
            SubmissionEventLogEntry::SUBMISSION_LOG_SUBMISSION_SUBMIT,
            'submission.event.submissionSubmitted'
        );
    }

    /**
     * Creates SubmissionAcknowledgement mailable and populates it with data
     */
    protected function setSubmissionAckMailable(Context $context, User $user): SubmissionAcknowledgement
    {
        $submissionAck = new SubmissionAcknowledgement($context, $this->submission);
        $submissionAckTemplate = Repo::emailTemplate()->getByKey($context->getId(), SubmissionAcknowledgement::getEmailTemplateKey());
        $submissionAck->from($this->context->getData('contactEmail'), $this->context->getData('contactName'))
            ->recipients([$user]);

        // Add primary contact and e-mail addresses as specified in the journal submission settings
        if ($this->context->getData('copySubmissionAckPrimaryContact')) {
            $submissionAck->bcc($context->getData('contactEmail'), $context->getData('contactName'));
        }

        $submissionAckAddresses = $this->context->getData('copySubmissionAckAddress');
        if (!empty($submissionAckAddresses)) {
            $submissionAckAddressArray = explode(',', $submissionAckAddresses);
            foreach ($submissionAckAddressArray as $submissionAckAddress) {
                $submissionAck->bcc($submissionAckAddress);
            }
        }

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByRoleId($this->context->getId(), Role::ROLE_ID_SUB_EDITOR);

        // Cycle through all the userGroups for this role
        while ($userGroup = $userGroups->next()) {
            // FIXME: #6692# Should this be getting users just for a specific user group?
            $collector = Repo::user()->getCollector();
            $collector->assignedTo($this->submissionId, WORKFLOW_STAGE_ID_SUBMISSION, $userGroup->getId());
            $users = Repo::user()->getMany($collector);
            foreach ($users as $user) {
                $submissionAck->bcc($user->getEmail(), $user->getFullName());
            }
        }

        $submissionAck
            ->subject($submissionAckTemplate->getLocalizedData('subject'))
            ->body($submissionAckTemplate->getLocalizedData('body'));

        return $submissionAck;
    }

    /**
     * Creates SubmissionAckNotAuthor mailable and populates it with data
     */
    protected function setSubmissionAckNotAuthorMailable(Context $context, User $user): SubmissionAcknowledgementNotAuthor
    {
        $submissionAckNotAuthor = new SubmissionAcknowledgementNotAuthor($context, $this->submission, $user);
        $submissionAckNotAuthorTemplate = Repo::emailTemplate()->getByKey($context->getId(), SubmissionAcknowledgementNotAuthor::getEmailTemplateKey());
        $submissionAckNotAuthor->from($this->context->getData('contactEmail'), $this->context->getData('contactName'))
            ->subject($submissionAckNotAuthorTemplate->getLocalizedData('subject'))
            ->body($submissionAckNotAuthorTemplate->getLocalizedData('body'));

        return $submissionAckNotAuthor;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\form\PKPSubmissionSubmitStep4Form', '\PKPSubmissionSubmitStep4Form');
}
