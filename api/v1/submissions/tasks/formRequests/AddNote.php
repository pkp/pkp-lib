<?php

/**
 * @file api/v1/submissions/formRequests/AddNote.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddNote
 *
 * @brief Handle API requests validation to handle discussion replies except the headnote.
 *
 */

namespace PKP\API\v1\submissions\tasks\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PKP\core\PKPApplication;
use PKP\editorialTask\EditorialTask;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class AddNote extends FormRequest
{
    // The task being edited
    protected EditorialTask $task;

    // The submission associated with the task
    protected Submission $submission;

    /**
     * @var array<StageAssignment> $stageAssignments associated with the submission to validate the participants of the task.
     */
    protected array $stageAssignments = [];

    public function rules(): array
    {
        $this->task = EditorialTask::find($this->route('taskId'));
        $this->submission = Repo::submission()->get($this->task->assocId);
        $this->stageAssignments = StageAssignment::with('userGroup')
            ->withSubmissionIds([$this->submission->getId()])
            ->withStageIds([$this->task->stageId])
            ->get()
            ->all();

        $currentUser = Application::get()->getRequest()?->getUser();

        return [
            'userId' => [
                'required',
                'numeric',
                Rule::exists('edit_task_participants', 'user_id')->where(function (Builder $query) {
                    $query->where('edit_task_id', $this->route('taskId'));
                }),
            ],
            'assocType' => ['required', Rule::in([PKPApplication::ASSOC_TYPE_QUERY])],
            'assocId' => [
                'required',
                Rule::exists('edit_tasks', 'edit_task_id'),
                Rule::in([$this->route('taskId')]),
            ],
            'contents' => ['required', 'string', 'max:65535'],
            'temporaryFileIds' => ['sometimes', 'array', 'nullable'],
            'temporaryFileIds.*' => [
                'numeric',
                Rule::exists('temporary_files', 'file_id')->where(
                    fn (Builder $query) =>
                    $query->where('user_id', $currentUser?->getId())
                )
            ],
            // The attachment of submission files to the task/discussion is allowed for managers and assigned editors/assistants only
            'submissionFileIds' => [
                'sometimes',
                'array',
                'nullable',
                Rule::prohibitedIf(function () {
                    $currentUser = Application::get()->getRequest()?->getUser();
                    if ($currentUser && $currentUser->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $this->submission->getData('contextId'))) {
                        return false;
                    }

                    $isAssignedEditor = false;
                    foreach ($this->stageAssignments as $stageAssignment) {
                        if ($stageAssignment->userId == $currentUser->getId() && in_array($stageAssignment->userGroup->roleId, [Role::ROLE_ID_ASSISTANT, Role::ROLE_ID_SUB_EDITOR])) {
                            $isAssignedEditor = true;
                            break;
                        }
                    }

                    return !$isAssignedEditor;
                })
            ],
            'submissionFileIds.*' => [
                'numeric',
                Rule::exists('submission_files', 'submission_file_id')->where(function (Builder $query) {
                    $query->where('submission_id', $this->submission->getId());
                })
            ]
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'userId' => Application::get()->getRequest()?->getUser()?->getId(),
            'assocType' => Application::ASSOC_TYPE_QUERY,
            'assocId' => $this->route('taskId'),
        ]);
    }
}
