<?php

/**
 * @file api/v1/editTaskTemplates/formRequests/AddTaskTemplate.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddTaskTemplate
 *
 * @brief Handle API requests validation for adding editorial template operations.
 *
 */

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PKP\editorialTask\enums\EditorialTaskDueInterval;
use PKP\editorialTask\enums\EditorialTaskType;

class AddTaskTemplate extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();

        // build a list of allowed stage IDs from values
        $stages = Application::getApplicationStages();
        $stageIds = array_values(array_unique(array_map('intval', array_values((array) $stages))));


        return [
            'type' => ['required', Rule::in(array_column(EditorialTaskType::cases(), 'value'))],
            'stageId' => ['required', 'integer', Rule::in($stageIds)],
            'title' => ['required', 'string', 'max:255'],
            'include' => ['boolean'],
            'dueInterval' => ['sometimes', 'nullable', 'string', Rule::in(array_column(EditorialTaskDueInterval::cases(), 'value'))],
            'description' => ['sometimes', 'nullable', 'string'],
            'userGroupIds' => ['required', 'array', 'min:1'],
            'userGroupIds.*' => [
                'integer',
                'distinct',
                Rule::exists('user_groups', 'user_group_id')
                    ->where(fn ($q) => $q->where('context_id', $contextId)),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $stageId = $this->input('stageId', null);
        $type = $this->input('type', null);
        $this->merge([
            'include' => filter_var($this->input('include', false), FILTER_VALIDATE_BOOLEAN),
            'userGroupIds' => array_values(array_map('intval', (array) $this->input('userGroupIds', []))),
            'stageId' => is_null($stageId) ? $stageId : (int) $stageId,
            'type' => is_null($type) ? null : (int) $type,
        ]);
    }
}
