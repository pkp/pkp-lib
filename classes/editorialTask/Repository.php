<?php

/**
 * @file classes/query/Repository.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @see EditorialTask
 *
 * @brief Operations for retrieving and modifying Query objects.
 */

namespace PKP\editorialTask;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\mail\Mailable;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;
use PKP\mail\mailables\DiscussionSubmission;
use PKP\mail\mailables\DiscussionReview;
use PKP\mail\mailables\DiscussionCopyediting;
use PKP\mail\mailables\DiscussionProduction;


class Repository
{
    /**
     * Retrieve a count of all open queries totalled by stage
     *
     * @param ?int[] $participantIds Only include queries with these participants
     *
     * @return array<int,int> [int $stageId => int $count]
     */
    public function countOpenPerStage(int $submissionId, ?array $participantIds = null): array
    {
        $counts = EditorialTask::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->when($participantIds !== null, function ($q) use ($participantIds) {
                $q->withParticipantIds($participantIds);
            })
            ->withClosed(false)
            ->selectRaw('stage_id, COUNT(stage_id) as count')
            ->groupBy('stage_id')
            ->get()
            ->mapWithKeys(fn ($row, $key) => [$row->stage_id => $row->count])
            ->toArray();

        return collect(Application::get()->getApplicationStages())
            ->mapWithKeys(fn ($stageId, $key) => [$stageId => $counts[$stageId] ?? 0])
            ->toArray();
    }

    /**
     * Sequentially renumber queries in their sequence order.
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     * @param int $assocId Assoc ID per assocType
     */
    public function resequence($assocType, $assocId): void
    {
        $result = EditorialTask::withAssoc($assocType, $assocId)
            ->orderBy('seq')
            ->get();

        $result->each(function (EditorialTask $item, int $key = 1) {
            $item->update(['seq' => $key]);
        });
    }

    /**
     * Start a query
     *
     * Inserts the query, assigns participants, and creates the head note
     *
     * @return int The new query id
     */
    public function addQuery(int $submissionId, int $stageId, string $title, string $content, User $fromUser, array $participantUserIds, int $contextId, bool $sendEmail = true): int
    {
        $maxSeq = EditorialTask::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->max('seq') ?? 0;

        $task = EditorialTask::create([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'stageId' => $stageId,
            'seq' => $maxSeq + 1,
            'createdBy' => $fromUser->getId(),
            'type' => EditorialTaskType::DISCUSSION->value,
            EditorialTask::ATTRIBUTE_PARTICIPANTS => array_map(
                fn (int $participantId) => ['userId' => $participantId],
                array_unique($participantUserIds)
            ),
            'title' => $title,
        ]);

        // Head note for this discussion, with a capturable messageId
        $headNote = Note::create([
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $task->id,
            'contents' => $content,
            'userId' => $fromUser->getId(),
            'isHeadnote' => true,
            'messageId' => Note::generateMessageId(),
        ]);

        // Add task for assigned participants
        $notificationMgr = new NotificationManager();

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        // need submission + context + stage mailables to send capturable email
        $submission = Repo::submission()->get($submissionId);
        $application = Application::get();
        $request = $application->getRequest();
        $context = $request?->getContext();

        $mailableMap = [
            WORKFLOW_STAGE_ID_SUBMISSION => DiscussionSubmission::class,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => DiscussionReview::class,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => DiscussionReview::class,
            WORKFLOW_STAGE_ID_EDITING => DiscussionCopyediting::class,
            WORKFLOW_STAGE_ID_PRODUCTION => DiscussionProduction::class,
        ];

        $mailableClass = $mailableMap[$stageId] ?? null;

        foreach ($task->participants()->get() as $participant) {
            $notification = $notificationMgr->createNotification(
                $participant->userId,
                Notification::NOTIFICATION_TYPE_NEW_QUERY,
                $contextId,
                Application::ASSOC_TYPE_QUERY,
                $task->id,
                Notification::NOTIFICATION_LEVEL_TASK
            );

            if (
                !$sendEmail|| !$notification || !$mailableClass || !$submission || !$context) {
                continue;
            }

            // Check if the user is unsubscribed
            $notificationSubscriptionSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                $participant->userId,
                $contextId
            );
            if (in_array(Notification::NOTIFICATION_TYPE_NEW_QUERY, $notificationSubscriptionSettings)) {
                continue;
            }

            $recipient = $participant->user;
            if (!$recipient) {
                continue;
            }

            /** @var \PKP\mail\Mailable $mailable */
            $mailable = new $mailableClass($context, $submission);

            $mailable
                ->sender($fromUser)
                ->recipients([$recipient])
                ->subject($title)
                ->body($content)
                ->allowUnsubscribe($notification)
                ->allowCapturableReply($headNote->messageId);

            Mail::send($mailable);
        }

        return $task->id;
    }

    /**
     * Create a query with a submission's comments for the editors
     *
     * Creates the query and assigns all participants
     *
     * @return int new query id
     */
    public function addCommentsForEditorsQuery(Submission $submission): int
    {
        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $participantUserIds = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([
                Role::ROLE_ID_MANAGER,
                Role::ROLE_ID_SUB_EDITOR,
                Role::ROLE_ID_ASSISTANT,
                Role::ROLE_ID_AUTHOR,
            ])
            ->withStageIds([$submission->getData('stageId')])
            ->get()
            ->pluck('user_id')
            ->all();

        // Replaces StageAssignmentDAO::getBySubmissionAndRoleIds
        $authorAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withRoleIds([Role::ROLE_ID_AUTHOR])
            ->withStageIds([$submission->getData('stageId')])
            ->get();

        $fromUser = $authorAssignments->isEmpty()
            ? Application::get()->getRequest()->getUser()
            : Repo::user()->get($authorAssignments->first()->userId);

        return $this->addQuery(
            $submission->getId(),
            $submission->getData('stageId'),
            __('submission.submit.coverNote'),
            $submission->getData('commentsForTheEditors'),
            $fromUser,
            $participantUserIds,
            $submission->getData('contextId')
        );
    }

    public function autoCreateFromTemplates(Submission $submission, int $stageId): void
    {
        $contextId = (int) $submission->getData('contextId');

        $templates = Template::query()
            ->byContextId($contextId)
            ->filterByStageId($stageId)
            ->filterByInclude(true)
            ->get();

        foreach ($templates as $template) {
            $templateId = (int) $template->id;

            if ($this->taskAlreadyCreatedFromTemplate($submission->getId(), $templateId)) {
                continue;
            }

            $task = $template->promote($submission, false); // no participants

            $maxSeq = (float) (EditorialTask::query()
                ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
                ->where('assoc_id', $submission->getId())
                ->max('seq') ?? 0);

            $task->seq = $maxSeq + 1;

            // createdBy left as default (NULL) for system-created tasks
            $task->save();
        }
    }

    private function taskAlreadyCreatedFromTemplate(int $submissionId, int $templateId): bool
    {
        return DB::table('edit_tasks')
            ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->where('assoc_id', $submissionId)
            ->where('edit_task_template_id', $templateId)
            ->exists();
    }


    /**
     * Deletes all tasks, notes, and notifications associated with the given submission ID.
     */
    public function deleteBySubmissionId(int $submissionId): void
    {
        $editorialTasks = EditorialTask::withAssoc(PKPApplication::ASSOC_TYPE_SUBMISSION, $submissionId)->get();
        $primaryKeyName = (new EditorialTask())->getKeyName();
        $taskIds = $editorialTasks->pluck($primaryKeyName)->all();

        if (!empty($taskIds)) {
            EditorialTask::whereIn($primaryKeyName, $taskIds)->delete();
            Note::whereIn('assoc_id', $taskIds)->delete();
            Notification::whereIn('assoc_id', $taskIds)->delete();
        }
    }

    public function notifyParticipantsOnNote(Note $note): void
    {
        // Only discussion notes
        if ($note->assocType !== PKPApplication::ASSOC_TYPE_QUERY) {
            return;
        }

        // skip headnote initial email handled when query is created
        if ($note->isHeadnote ?? false) {
            return;
        }

        $task = EditorialTask::find($note->assocId);
        if (!$task) {
            return;
        }

        $submission = Repo::submission()->get($task->assocId);
        if (!$submission) {
            return;
        }

        $application = Application::get();
        $request = $application->getRequest();
        $context = $request->getContext();
        if (!$context) {
            return;
        }

        $sender = Repo::user()->get($note->userId ?? null);
        if (!$sender) {
            return;
        }

        $headNote = Repo::note()->getHeadNote($task->id);
        $threadAnchorMessageId = $headNote?->messageId;
        $title = $headNote?->title ?: $task->title;
        $subject = $title
            ? __('common.re') . ' ' . $title
            : __('common.re');


        $participantIds = $task->participants()
            ->pluck('user_id')
            ->all();

        if (empty($participantIds)) {
            return;
        }

        /** @var NotificationSubscriptionSettingsDAO $notificationSubscriptionSettingsDao */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        $notificationManager = new NotificationManager();

        // attachments for this note (if any)
        $submissionFiles = Repo::submissionFile()
            ->getCollector()
            ->filterByAssoc(PKPApplication::ASSOC_TYPE_NOTE, [$note->id])
            ->filterBySubmissionIds([$submission->getId()])
            ->getMany();

        // Stage -> mailable map (same as StageMailable)
        $mailableMap = [
            WORKFLOW_STAGE_ID_SUBMISSION => DiscussionSubmission::class,
            WORKFLOW_STAGE_ID_INTERNAL_REVIEW => DiscussionReview::class,
            WORKFLOW_STAGE_ID_EXTERNAL_REVIEW => DiscussionReview::class,
            WORKFLOW_STAGE_ID_EDITING => DiscussionCopyediting::class,
            WORKFLOW_STAGE_ID_PRODUCTION => DiscussionProduction::class,
        ];

        if (!isset($mailableMap[$task->stageId])) {
            return;
        }

        $mailableClass = $mailableMap[$task->stageId];

        foreach ($participantIds as $userId) {
            if ($userId === $sender->getId()) {
                continue;
            }

            // clear previous "query activity" notifications for this user/query
            Notification::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $task->id)
                ->withUserId($userId)
                ->withType(Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY)
                ->withContextId((int) $context->getId())
                ->delete();

            $recipient = Repo::user()->get($userId);
            if (!$recipient) {
                continue;
            }

            // create notification
            $notification = $notificationManager->createNotification(
                $userId,
                Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY,
                (int) $context->getId(),
                PKPApplication::ASSOC_TYPE_QUERY,
                $task->id,
                Notification::NOTIFICATION_LEVEL_TASK
            );

            if (!$notification) {
                continue;
            }

            // respect email notification settings
            $blocked = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                $userId,
                (int) $context->getId()
            );

            if (in_array(Notification::NOTIFICATION_TYPE_QUERY_ACTIVITY, $blocked)) {
                continue;
            }

            /** @var \PKP\mail\Mailable $mailable */
            $mailable = new $mailableClass($context, $submission);

            $mailable
                ->sender($sender)
                ->recipients([$recipient])
                ->subject($subject)
                ->body($note->contents)
                ->allowUnsubscribe($notification)
                ->allowCapturableReply(
                    $note->messageId,
                    $threadAnchorMessageId && $threadAnchorMessageId !== $note->messageId ? $threadAnchorMessageId : null,
                    $threadAnchorMessageId ? [$threadAnchorMessageId] : []
                );

            $submissionFiles->each(
                fn ($item) => $mailable->attachSubmissionFile(
                    $item->getId(),
                    $item->getData('name')
                )
            );

            Mail::send($mailable);
        }
    }


    public function removeParticipantFromSubmissionTasks(int $submissionId, int $userId, int $contextId): void
    {
        $user = Repo::user()->get($userId);

        // Non-managerial only
        if ($user && $user->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $contextId)) {
            return;
        }

        $taskIds = EditorialTask::query()
            ->where('assoc_type', PKPApplication::ASSOC_TYPE_SUBMISSION)
            ->where('assoc_id', $submissionId)
            ->pluck('edit_task_id')
            ->all();

        if (empty($taskIds)) {
            return;
        }

        Participant::query()
            ->whereIn('edit_task_id', $taskIds)
            ->where('user_id', $userId)
            ->delete();
    }
}
