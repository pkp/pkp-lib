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
use PKP\core\PKPApplication;
use PKP\core\traits\ResourceWithData;
use PKP\editorialTask\enums\EditorialTaskStatus;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;

class TaskResource extends JsonResource
{
    use ResourceWithData;

    public function toArray(Request $request)
    {
        [$users, $submissionFiles] = $this->getData('users', 'submissionFiles');

        $createdBy = $users->first(fn (User $user) => $user->getId() === $this->createdBy); /** @var User $createdBy */
        $startedBy = $users->first(fn (User $user) => $user->getId() === $this->startedBy); /** @var User $startedBy */
        $submissionFiles = $submissionFiles->collect()->filter(
            fn (SubmissionFile $submissionFile) =>
             $submissionFile->getAssocType() == PKPApplication::ASSOC_TYPE_QUERY && (int) $submissionFile->getData('assocId') == $this->id
        );

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
            'notes' => NoteResource::collection(resource: $this->notes->sortBy('dateCreated'), data: array_merge($this->data, [
                'parentResource' => $this,
            ])),
            'submissionFileIds' => $submissionFiles->isNotEmpty() ?
                $submissionFiles->map(fn (SubmissionFile $submissionFile) => $submissionFile->getId())->toArray() :
                null
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
            'submissionFiles'
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
