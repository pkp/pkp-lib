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

namespace PKP\API\v1\submissions\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Closure;
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
            EditorialTask::ATTRIBUTE_PARTICIPANTS => [
                'required',
                'array',
                function (string $attribute, array $value, Closure $fail) {
                    $responsibles = array_filter(Arr::pluck($this->input('participants'), 'isResponsible'));
                    if (count($responsibles) > 1) {
                        return $fail('There should be the only one user responsible for the task');
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

                        if (!in_array($reviewAssignment->getReviewerId(), $participants)) {
                            continue;
                        }

                        if (in_array(
                            $reviewAssignment->getReviewMethod(),
                            [ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS, ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS]
                        )
                        ) {
                            $blindedReviewerIds[] = $reviewAssignment->getReviewerId();
                        } else {
                            $nonBlindedReviewerIds[] = $reviewAssignment->getReviewerId();
                        }
                    }

                    if (empty($blindedReviewerIds)) {
                        return true;
                    }

                    // Don't disclose anonymous reviewer to other reviewers
                    if (count($blindedReviewerIds) > 1 && !empty($nonBlindedReviewerIds)) {
                        return $fail('Cannot disclose the identity of reviewers in the task/discussion.');
                    }

                    foreach ($this->stageAssignments as $stageAssignment) {
                        if (!in_array($stageAssignment->userId, $participants)) {
                            continue;
                        }

                        if (!in_array(Role::ROLE_ID_AUTHOR, $stageAssignment->userGroup->role_id)) {
                            continue;
                        }

                        // Shouldn't allow participation of authors if there are blinded reviewers
                        $fail('Cannot allow participation of authors together with reviewers in a task/discussion during anonymous reviews.');
                    }

                    return true;
                },
                function (string $attribute, array $value, Closure $fail) {
                    if ($this->input('type') == EditorialTaskType::TASK->value && count($value) < 1) {
                        $fail('At least one participant is required for a task.');
                    }

                    if ($this->input('type') == EditorialTaskType::DISCUSSION->value && count($value) < 2) {
                        $fail('At least two participants are required for a discussion.');
                    }
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
                        return $fail('Participant must be assigned to the submission in the current stage or be a reviewer.');
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
}
