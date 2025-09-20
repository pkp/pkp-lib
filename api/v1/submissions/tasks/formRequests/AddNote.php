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
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PKP\core\PKPApplication;

class AddNote extends FormRequest
{
    public function rules(): array
    {
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
