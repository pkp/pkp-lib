<?php

/**
 * @file api/v1/submissions/resources/TaskResource.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class TaskResource
 *
 * @brief Transforms the API response of the editorial task and discussion into the desired format
 *
 */

namespace PKP\API\v1\submissions\tasks\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use PKP\core\PKPApplication;
use PKP\core\traits\ResourceWithData;
use PKP\editorialTask\enums\EditorialTaskStatus;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\log\event\EventLogEntry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\note\Note;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class TaskResource extends JsonResource
{
    use ResourceWithData;

    public function toArray(Request $request)
    {
        [$users, $submissionFiles, $stageAssignments, $submission, $fileGenres, $activities] = $this->getData('users', 'submissionFiles', 'stageAssignments', 'submission', 'fileGenres', 'activities');

        $createdBy = $users->first(fn (User $user) => $user->getId() === $this->createdBy); /** @var User $createdBy */
        $startedBy = $users->first(fn (User $user) => $user->getId() === $this->startedBy); /** @var User $startedBy */

        $notes = $this->notes->sortBy('dateCreated');

        $submissionFiles = $submissionFiles->collect()->filter(
            fn (SubmissionFile $submissionFile) => $submissionFile->getAssocType() == PKPApplication::ASSOC_TYPE_NOTE &&
                in_array(
                    (int) $submissionFile->getData('assocId'),
                    $notes->pluck((new Note())->getKeyName())->toArray()
                )
        );

        $notesCollection = NoteResource::collection(resource: $notes, data: array_merge($this->data, [
            'parentResource' => $this,
            'submissionFiles' => $submissionFiles,
            'users' => $users,
            'stageAssignments' => $stageAssignments,
            'submission' => $submission,
            'fileGenres' => $fileGenres,
        ]));

        $activities = $activities->filter(fn (EventLogEntry $activity) => $activity->getAssocId() == $this->id);
        $latestActivity = $activities->first(); /** @var ?EventLogEntry|null $latestActivity */
        $latestActivities = [];
        // always add an overdue at the start of the list
        if ($dateDue = $this->dateDue) {
            $overdue = Carbon::now()->gt($dateDue);
            if ($overdue) {
                $latestActivities[] = [
                    'id' => 0, // Do not have
                    'message' => __('submission.event.task.overdue'),
                    'type' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_TASK_OVERDUE
                ];
            }
        }

        if ($latestActivity) {
            $taskDateCreated = $latestActivity->getData('taskDateCreated');
            $taskDateStarted = $latestActivity->getData('taskDateStarted');
            $taskDateClosed = $latestActivity->getData('taskDateClosed');
            $activityMessage = __($latestActivity->getMessage(), [
                'taskType' => EditorialTaskType::from($this->type)->label(),
                'username' => $latestActivity->getData('username'),
                'taskDateCreated' => $taskDateCreated ? Carbon::parse($taskDateCreated)->format('Y-m-d') : null,
                'taskDateStarted' => $taskDateStarted ? Carbon::parse($taskDateStarted)->format('Y-m-d') : null,
                'taskDateClosed' => $taskDateClosed ? Carbon::parse($taskDateClosed)->format('Y-m-d') : null,
                'taskDateReplied' => $notes->first()?->dateCreated?->format('Y-m-d'),
            ]);
            $latestActivities[] = [
                'id' => $latestActivity->getId(),
                'message' => $activityMessage,
                'type' => $latestActivity->getEventType()
            ];
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'assocType' => $this->assocType,
            'assocId' => $this->assocId,
            'stageId' => $this->stageId,
            'status' => $this->determineStatus(),
            'createdBy' => $this->createdBy,
            'createdByName' => $createdBy?->getFullName(),
            'createdByUsername' => $createdBy?->getUsername(),
            'dateDue' => $this->dateDue?->format('Y-m-d'),
            'dateStarted' => $this->dateStarted?->format('Y-m-d'),
            'startedBy' => $this->startedBy,
            'startedByName' => $startedBy?->getFullName(),
            'dateClosed' => $this->dateClosed?->format('Y-m-d'),
            'title' => $this->title,
            'participants' => EditorialTaskParticipantResource::collection(resource: $this->participants, data: $this->data),
            'notes' => $notesCollection,
            'latestActivities' => $latestActivities,
        ];
    }

    /**
     * @inheritDoc
     */
    protected static function requiredKeys(): array
    {
        return [
            'submission',
            'users',
            'userGroups',
            'stageAssignments',
            'reviewAssignments',
            'submissionFiles',
            'fileGenres',
            'activities',
        ];
    }

    /**
     * Determine the status of the task based on its type and dates.
     */
    protected function determineStatus()
    {
        if ($this->type == EditorialTaskType::TASK->value) {
            return $this->dateClosed ? EditorialTaskStatus::CLOSED->value : ($this->dateStarted ? EditorialTaskStatus::IN_PROGRESS->value : EditorialTaskStatus::PENDING->value);
        }

        return $this->dateClosed ? EditorialTaskStatus::CLOSED->value : EditorialTaskStatus::IN_PROGRESS->value;
    }
}
