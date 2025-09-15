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
use APP\submission\Submission;
use Illuminate\Validation\Rule;
use PKP\core\PKPApplication;

class AddTask extends EditTask
{
    public function rules(): array
    {
        $parentRules = parent::rules();

        return array_merge($parentRules, [
            'createdBy' => ['sometimes', 'nullable', Rule::exists('users', 'user_id')],
            'stageId' => ['required','numeric', Rule::in(Application::getApplicationStages())],
            'assocType' => ['required', Rule::in([PKPApplication::ASSOC_TYPE_SUBMISSION])],
            'assocId' => [
                'required',
                Rule::exists('submissions', 'submission_id'),
                Rule::in([$this->route('submissionId')]),
            ],
        ]);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'createdBy' => Application::get()->getRequest()?->getUser()?->getId(),
            'assocType' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            'assocId' => $this->route('submissionId'),
            'description' => $this->input('description', ''),
        ]);
    }

    protected function setSubmission(): Submission
    {
        return Repo::submission()->get((int) $this->all(['assocId']));
    }

    protected function getStageId(): int
    {
        return (int) $this->input('stageId');
    }
}
