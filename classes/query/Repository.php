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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PKP\db\DAORegistry;
use PKP\mail\Mailable;
use PKP\note\Note;
use PKP\notification\Notification;
use PKP\notification\NotificationSubscriptionSettingsDAO;
use PKP\QueryParticipant\QueryParticipant;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\user\User;

class Repository
{
    /**
     * Fetch a query by symbolic info, building it if needed.
     */
    public function build(int $assocType, int $assocId, int $userId, int $stageId, float $seq, int $closed): Query
    {
        return Query::withUserId($userId)
            ->withAssoc($assocType, $assocId)
            ->firstOr(fn() => Query::create([
                'assocType' => $assocType,
                'assocId' => $assocId,
                'userId' => $userId,
                'stageId' => $stageId,
                'seq' => $seq,
                'closed' => $closed
            ]));
    }

    /**
     * Retrieve a count of all open queries totalled by stage
     *
     * @param int[] $participantIds Only include queries with these participants
     *
     * @return array<int,int> [int $stageId => int $count]
     */
    public function countOpenPerStage(int $submissionId, ?array $participantIds = null)
    {
        $counts = DB::table('queries as q')
            ->when($participantIds !== null, function ($q) use ($participantIds) {
                $q->join('query_participants as qp', 'q.query_id', '=', 'qp.query_id')
                    ->whereIn('qp.user_id', $participantIds);
            })
            ->where('q.assoc_type', '=', Application::ASSOC_TYPE_SUBMISSION)
            ->where('q.assoc_id', '=', $submissionId)
            ->where('q.closed', '=', 0)
            ->select(['q.stage_id', DB::raw('COUNT(q.stage_id) as count')])
            ->groupBy(['q.stage_id'])
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
        $result = $this->withAssoc($assocType, $assocId)
            ->orderBy('seq')
            ->get();

        for ($i = 1; $row = $result->current(); $i++) {
            $this->where('queryId', $row->queryId)
                ->update(['seq' => $i]);
            $result->next();
        }
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
        $query = Query::create([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'stageId' => $stageId,
            'seq' => REALLY_BIG_NUMBER
        ]);

        $this->resequence(Application::ASSOC_TYPE_SUBMISSION, $submissionId);

        foreach ($participantUserIds as $participantUserId) {
            QueryParticipant::create([
                'queryId' => $query->id,
                'userId' => $participantUserId
            ]);
        }

        Note::create([
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $query->id,
            'contents' =>  $content,
            'title' =>  $title,
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
            ->pluck('userId')
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
