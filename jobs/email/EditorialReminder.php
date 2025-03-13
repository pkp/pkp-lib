<?php

/**
 * @file jobs/email/EditorialReminder.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditorialReminder
 *
 * @ingroup jobs
 *
 * @brief Class to handle a job to send an editorial reminder
 */

namespace PKP\jobs\email;

use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use PKP\security\Role;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\facades\Locale;
use PKP\jobs\BaseJob;
use PKP\mail\mailables\EditorialReminder as MailablesEditorialReminder;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\user\User;
use PKP\workflow\WorkflowStageDAO;

class EditorialReminder extends BaseJob
{
    protected int $editorId;
    protected int $contextId;

    public function __construct(int $editorId, int $contextId)
    {
        parent::__construct();

        $this->editorId = $editorId;
        $this->contextId = $contextId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /** @var Context $context */
        $context = app()->get('context')->get($this->contextId);
        $editor = Repo::user()->get($this->editorId);

        // Context or user was removed since job was created, or the user was disabled
        if (!$context || !$editor) {
            return;
        }

        // If the user has been removed form manager or editor role since the job was created
        if (!$editor->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR], $context->getId())) {
            return;
        }

        if (!$this->isSubscribed()) {
            return;
        }

        // Don't use the request locale because this job is
        // run during a scheduled task
        $requestLocale = Locale::getLocale();
        Locale::setLocale($this->getLocale($editor, $context));

        $submissionIds = Repo::submission()
            ->getCollector()
            ->assignedTo([$this->editorId])
            ->filterByContextIds([$this->contextId])
            ->filterByStatus([Submission::STATUS_QUEUED])
            ->filterByIncomplete(false)
            ->getIds();

        $outstanding = [];
        $submissions = [];

        /** @var int $submissionId */
        foreach ($submissionIds as $submissionId) {
            $submission = Repo::submission()->get($submissionId);
            $submissions[$submissionId] = $submission;

            if ($submission->getData('stageId') == WORKFLOW_STAGE_ID_SUBMISSION) {
                $outstanding[$submissionId] = __('editor.submission.status.waitingInitialReview');
                continue;
            }

            if (in_array($submission->getData('stageId'), [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW])) {
                /** @var ReviewRoundDAO $reviewRoundDao */
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
                $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $submission->getData('stageId'));
                $status = $reviewRound->determineStatus();

                if ($status === ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS) {
                    $outstanding[$submissionId] = __('editor.submission.roundStatus.pendingReviewers');
                    continue;
                }

                if ($status === ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWS) {
                    $outstanding[$submissionId] = __('editor.submission.roundStatus.pendingReviews');
                    continue;
                }

                if ($status === ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_READY) {
                    $outstanding[$submissionId] = __('editor.submission.roundStatus.reviewsReady');
                    continue;
                }

                if ($status === ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED) {
                    $outstanding[$submissionId] = __('editor.submission.roundStatus.reviewsCompleted');
                    continue;
                }

                if ($status === ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE) {
                    $outstanding[$submissionId] = __('editor.submission.roundStatus.reviewOverdue');
                    continue;
                }

                if ($status === ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED) {
                    $outstanding[$submissionId] = __('editor.submission.roundStatus.revisionsSubmitted');
                    continue;
                }
            }

            if (in_array($submission->getData('stageId'), [WORKFLOW_STAGE_ID_EDITING, WORKFLOW_STAGE_ID_PRODUCTION])) {
                $lastActivityTimestamp = Carbon::parse($submission->getData('dateLastActivity'))->endOfDay();
                $comparingTimestamp = Carbon::today()->endOfDay()->subDays(30);
                if ($comparingTimestamp->gt($lastActivityTimestamp)) {
                    /** @var WorkflowStageDAO $workflowStageDao */
                    $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO');
                    $outstanding[$submissionId] = __(
                        'editor.submission.status.inactiveDaysInStage',
                        [
                            'days' => 30,
                            'stage' => __($workflowStageDao->getTranslationKeyFromId($submission->getData('stageId')))
                        ]
                    );
                }
            }

            if (count($outstanding) > 20) {
                break;
            }
        }

        if (empty($outstanding)) {
            return;
        }

        $notificationManager = new NotificationManager();
        $notification = $notificationManager->createNotification(
            $this->editorId,
            Notification::NOTIFICATION_TYPE_EDITORIAL_REMINDER,
            $this->contextId
        );

        $mailable = new MailablesEditorialReminder($context);
        $emailTemplate = Repo::emailTemplate()->getByKey($context->getId(), $mailable::getEmailTemplateKey());

        $mailable
            ->setOutstandingTasks($outstanding, $submissions, $submissionIds->count())
            ->from($context->getContactEmail(), $context->getLocalizedName(Locale::getLocale()))
            ->recipients([$editor])
            ->subject($emailTemplate->getLocalizedData('subject'))
            ->body($emailTemplate->getLocalizedData('body'))
            ->allowUnsubscribe($notification);

        Mail::send($mailable);

        // Restore the current locale after the email is sent
        Locale::setLocale($requestLocale);
    }

    /**
     * Is this editor subscribed to this notification type?
     */
    protected function isSubscribed(): bool
    {
        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao  */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');
        $blockedEmails = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
            NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
            $this->editorId,
            $this->contextId
        );

        return !in_array(Notification::NOTIFICATION_TYPE_EDITORIAL_REMINDER, $blockedEmails);
    }

    /**
     * Get the locale to use with this email
     *
     * Returns the context's primary locale, or the first locale
     * supported by the context and the user.
     *
     * @return string Locale key. Example: en
     */
    protected function getLocale(User $editor, Context $context): string
    {
        $locale = $context->getPrimaryLocale();

        // A user's locales may not be an array due to bug with data structure
        // See: https://github.com/pkp/pkp-lib/issues/8023
        if ($editor->getLocales() === false) {
            return $locale;
        }

        $locales = array_intersect($editor->getLocales(), $context->getSupportedLocales());
        if (!empty($locales) && !in_array($context->getPrimaryLocale(), $locales)) {
            $locale = $locales[0];
        }

        return $locale;
    }
}
