<?php

/**
 * @file api/v1/reviewers/suggestions/formRequests/addTask.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2003-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddTask
 *
 * @brief Handle API requests validation for adding a task or discussion.
 *
 */

namespace PKP\API\v1\submissions\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use PKP\core\PKPApplication;
use PKP\editorialTask\EditorialTask;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;

class AddTask extends FormRequest
{
    public function rules()
    {
        $userDao = Repo::user()->dao;
        $currentUser = Application::get()->getRequest()?->getUser();
        $input = $this->all();
        $submission = Repo::submission()->get((int) $input['assocId']);

        return [
            'createdBy' => ['sometimes', 'nullable', Rule::exists('users', 'user_id')],
            'status' => ['required', Rule::in([EditorialTask::STATUS_NEW])],
            'assocType' => ['required', Rule::in([PKPApplication::ASSOC_TYPE_SUBMISSION])],
            'assocId' => ['required', Rule::exists('submissions', 'submission_id')],
            'stageId' => ['required','numeric', Rule::in(Application::getApplicationStages())],
            'type' => ['required', Rule::in([EditorialTask::TYPE_DISCUSSION, EditorialTask::TYPE_TASK])],
            'dateDue' => [
                Rule::requiredIf(fn () => $this->input('type') == EditorialTask::TYPE_TASK),
                Rule::prohibitedIf(fn () => $this->input('type') == EditorialTask::TYPE_DISCUSSION),
                'date',
                'after:today',
            ],
            EditorialTask::ATTRIBUTE_PARTICIPANTS => [
                'required',
                'array',
                function (string $attribute, array $value, Closure $fail) {
                    $reponsibles = array_filter(Arr::pluck($this->input('participants'), 'isResponsible'));
                    if (count($reponsibles) > 1) {
                        return $fail('There should be the only one user responsible for the task');
                    }

                    return true;
                }
            ],
            EditorialTask::ATTRIBUTE_PARTICIPANTS . '.*' => ['required', 'array:userId,isResponsible',],
            EditorialTask::ATTRIBUTE_PARTICIPANTS . '.*.userId' => [
                'required',
                'numeric',
                'distinct',
                Rule::exists($userDao->table, $userDao->primaryKeyColumn),
                function (string $attribute, int $value, Closure $fail) use ($submission, $currentUser) {
                    // Participant must be assigned to the submission
                    if (StageAssignment::withSubmissionIds([$submission->getId()])->withUserId($value)->exists()) {
                        return true;
                    }

                    // If the creator is manager or admin, allow participation
                    if ($currentUser && $currentUser->hasRole([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $submission->getData('contextId'))) {
                        return true;
                    }

                    // TODO allow assigning reviewers if it doesn't break anonymity

                    return $fail();
                },
            ],
            // represents participant who is responsible for the task completion
            EditorialTask::ATTRIBUTE_PARTICIPANTS . '.*.isResponsible' => [
                'sometimes',
                'boolean',
                Rule::requiredIf(fn () => $this->input('type') == EditorialTask::TYPE_TASK),
                Rule::prohibitedIf(fn () => $this->input('type') == EditorialTask::TYPE_DISCUSSION),
            ],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'createdBy' => Application::get()->getRequest()?->getUser()?->getId(),
            'status' => EditorialTask::STATUS_NEW,
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $this->route('submissionId'),
        ]);
    }
}
