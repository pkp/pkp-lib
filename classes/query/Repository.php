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
 * @see Query
 *
 * @brief Operations for retrieving and modifying Query objects.
 */

namespace PKP\query;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use APP\submission\Submission;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\mail\Mailable;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

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
        $counts = Query::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->when($participantIds !== null, function ($q) use ($participantIds) {
                $q->withUserIds($participantIds);
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
        $result = Query::withAssoc($assocType, $assocId)
            ->orderBy('seq')
            ->get();

        $result->each(function (Query $item, int $key = 1) {
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
        $maxSeq = Query::withAssoc(Application::ASSOC_TYPE_SUBMISSION, $submissionId)
            ->max('seq') ?? 0;

        $query = Query::create([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'stageId' => $stageId,
            'seq' => $maxSeq + 1
        ]);

        $addParticipants = [];
        foreach (array_unique($participantUserIds) as $participantUserId) {
            $addParticipants[] = ([
                'query_id' => $query->id,
                'user_id' => $participantUserId
            ]);
        }
        QueryParticipant::insert($addParticipants);

        Note::create([
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $query->id,
            'title' =>  $title,
            'contents' =>  $content,
            'userId' =>  $fromUser->getId(),
        ]);

        // Add task for assigned participants
        $notificationMgr = new NotificationManager();

        /** @var NotificationSubscriptionSettingsDAO */
        $notificationSubscriptionSettingsDao = DAORegistry::getDAO('NotificationSubscriptionSettingsDAO');

        foreach ($participantUserIds as $participantUserId) {
            $notificationMgr->createNotification(
                Application::get()->getRequest(),
                $participantUserId,
                Notification::NOTIFICATION_TYPE_NEW_QUERY,
                $contextId,
                Application::ASSOC_TYPE_QUERY,
                $query->id,
                Notification::NOTIFICATION_LEVEL_TASK
            );

            if (!$sendEmail) {
                continue;
            }

            // Check if the user is unsubscribed
            $notificationSubscriptionSettings = $notificationSubscriptionSettingsDao->getNotificationSubscriptionSettings(
                NotificationSubscriptionSettingsDAO::BLOCKED_EMAIL_NOTIFICATION_KEY,
                $participantUserId,
                $contextId
            );
            if (in_array(Notification::NOTIFICATION_TYPE_NEW_QUERY, $notificationSubscriptionSettings)) {
                continue;
            }

            $recipient = Repo::user()->get($participantUserId);
            $mailable = new Mailable();
            $mailable->to($recipient->getEmail(), $recipient->getFullName());
            $mailable->from($fromUser->getEmail(), $fromUser->getFullName());
            $mailable->subject($title);
            $mailable->body($content);

            Mail::send($mailable);
        }

        return $query->id;
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
}
