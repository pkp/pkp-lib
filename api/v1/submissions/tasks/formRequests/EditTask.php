<?php

/**
 * @file api/v1/reviewers/suggestions/formRequests/editTask.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EditTask
 *
 * @brief Handle API requests validation for editing a task or discussion.
 *
 */

namespace PKP\API\v1\submissions\tasks\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use PKP\editorialTask\EditorialTask;
use PKP\editorialTask\enums\EditorialTaskType;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;

class EditTask extends FormRequest
{
    // The task being edited
    protected ?EditorialTask $task = null;

    // The submission associated with the task
    protected Submission $submission;

    /**
     * @var array<ReviewAssignment> $reviewAssignments associated with the submission
     * This is used to validate the participants of the task to avoid breaking the anonymity in blinded reviews.
     */
    protected array $reviewAssignments;

    /**
     * @var array<StageAssignment> $stageAssignments associated with the submission to validate the participants of the task.
     */
    protected array $stageAssignments;


    public function rules(): array
    {
        $userDao = Repo::user()->dao;
        $this->task = EditorialTask::find($this->route('taskId'));
        $this->submission = $this->setSubmission();
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterBySubmissionIds([$this->submission->getId()])
            ->getMany()
            ->toArray();

        $this->stageAssignments = StageAssignment::with('userGroup')
            ->withSubmissionIds([$this->submission->getId()])
            ->withStageIds([$this->getStageId()])
            ->get()
            ->all();

        return [
            'type' => ['required', Rule::in([EditorialTaskType::DISCUSSION->value, EditorialTaskType::TASK->value])],
            'title' => ['required', 'string', 'max:255'],
            'dateDue' => [
                Rule::requiredIf(fn () => $this->input('type') == EditorialTaskType::TASK->value),
                Rule::prohibitedIf(fn () => $this->input('type') == EditorialTaskType::DISCUSSION->value),
                Rule::date()->format('Y-m-d'),
                'after:today',
            ],
            EditorialTask::ATTRIBUTE_HEADNOTE => ['sometimes', 'string'],
            EditorialTask::ATTRIBUTE_PARTICIPANTS => [
                'required',
                'array',
                // Check responsible participant for the task completion
                function (string $attribute, array $value, Closure $fail) {
                    // No need to check responsible for discussions
                    if ($this->input('type') == EditorialTaskType::DISCUSSION->value) {
                        return true;
                    }

                    $responsibles = array_filter(Arr::pluck($this->input('participants'), 'isResponsible'));
                    if (count($responsibles) != 1) {
                        return $fail(__('submission.task.validation.error.participant.responsible'));
                    }

                    return true;
                },
                /**
                 * Handling validation of task in respect of anonymity in blinded reviews.
                 * The following rules are applied for anonymous reviews:
                 * 1. Don't allow participation of reviewers authors in the task together
                 * 2. Don't allow participation of two or more reviewers
                 */
                function (string $attribute, array $value, Closure $fail) {
                    if (!in_array($this->getStageId(), Application::getApplicationStages())) {
                        return true;
                    }

                    if (empty($this->reviewAssignments)) {
                        return true;
                    }

                    $participants = Arr::pluck($this->input('participants'), 'userId');

                    $blindedReviewerIds = [];
                    $nonBlindedReviewerIds = [];
                    foreach ($this->reviewAssignments as $reviewAssignment) {
                        $reviewerId = $reviewAssignment->getReviewerId();

                        if (!in_array($reviewerId, $participants)) {
                            continue;
                        }

                        if (in_array(
                            $reviewAssignment->getReviewMethod(),
                            [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS]
                        )) {
                            $blindedReviewerIds[$reviewerId] = true;
                        } else {
                            $nonBlindedReviewerIds[$reviewerId] = true;
                        }
                    }

                    if (empty($blindedReviewerIds)) {
                        return true;
                    }

                    // Don't disclose anonymous reviewer to other reviewers
                    if (count($blindedReviewerIds) > 1 || !empty($nonBlindedReviewerIds)) {
                        return $fail(__('submission.task.validation.error.reviewer.anonymous'));
                    }

                    foreach ($this->stageAssignments as $stageAssignment) {
                        if (!in_array($stageAssignment->userId, $participants)) {
                            continue;
                        }

                        if ($stageAssignment->userGroup->roleId != Role::ROLE_ID_AUTHOR) {
                            continue;
                        }

                        // Shouldn't allow participation of authors if there are blinded reviewers
                        $fail(__('submission.task.validation.error.review.anonymous'));
                    }

                    return true;
                },
                function (string $attribute, array $value, Closure $fail) {
                    if ($this->input('type') == EditorialTaskType::TASK->value && count($value) < 1) {
                        $fail(__('submission.task.validation.error.participant.required'));
                    }

                    if ($this->input('type') == EditorialTaskType::DISCUSSION->value && count($value) < 2) {
                        $fail(__('submission.task.validation.error.participants.required'));
                    }
                },

                // Check if the task creator is among participants
                function (string $attribute, array $value, Closure $fail) {
                    $participantIds = Arr::pluck($this->input('participants'), 'userId');
                    $creatorId = $this->getCreatorId();

                    if ($creatorId === null) {
                        return true; // We allow absent creator when task is automatically created
                    }

                    if (!in_array($this->getCreatorId(), $participantIds)) {
                        return $fail(__('submission.task.validation.error.participant.creator'));
                    }

                    return true;
                }
            ],
            EditorialTask::ATTRIBUTE_PARTICIPANTS . '.*' => [
                'required',
                'array:userId,isResponsible',
            ],
            EditorialTask::ATTRIBUTE_PARTICIPANTS . '.*.userId' => [
                'required',
                'numeric',
                'distinct',
                Rule::exists($userDao->table, $userDao->primaryKeyColumn),
                function (string $attribute, int $participantId, Closure $fail) {

                    // Admins and managers can participate in any task
                    $participant = Repo::user()->get($participantId);
                    if ($participant && $participant->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $this->submission->getData('contextId'))) {
                        return true;
                    }

                    // Participant must be assigned to the submission in the correspondent stage
                    $isAssigned = false;
                    foreach ($this->stageAssignments as $stageAssignment) {
                        if ($stageAssignment->userId == $participantId) {
                            $isAssigned = true;
                            break;
                        }
                    }

                    // Allow assigning reviewers if it doesn't break anonymity
                    $isReviewer = false;
                    if (in_array($this->getStageId(), Application::getApplicationStages())) {
                        foreach ($this->reviewAssignments as $assignment) {
                            if ($assignment->getReviewerId() == $participantId) {
                                $isReviewer = true;
                                break;
                            }
                        }
                    }

                    if (!($isAssigned || $isReviewer)) {
                        return $fail(__('submission.task.validation.error.assignment.required'));
                    }

                    return true;
                },
            ],
            // represents participant who is responsible for the task completion
            EditorialTask::ATTRIBUTE_PARTICIPANTS . '.*.isResponsible' => [
                'sometimes',
                'boolean',
                Rule::requiredIf(fn () => $this->input('type') == EditorialTaskType::TASK->value),
                Rule::prohibitedIf(fn () => $this->input('type') == EditorialTaskType::DISCUSSION->value),
            ],
            'temporaryFileIds' => ['sometimes', 'array', 'nullable'],
            'temporaryFileIds.*' => [
                'numeric',
                Rule::exists('temporary_files', 'file_id')->where(function (Builder $query) {
                    $query->where('user_id', Application::get()->getRequest()?->getUser()?->getId());
                })
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

    /**
     * Get the submission for the validation purpose.
     */
    protected function setSubmission(): Submission
    {
        return Repo::submission()->get($this->task->assocId);
    }

    protected function getStageId(): int
    {
        return $this->task->stageId;
    }

    protected function getCreatorId(): ?int
    {
        return $this->task->createdBy;
    }
}
