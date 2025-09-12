<?php

/**
 * @file api/v1/editTaskTemplates/formRequests/AddEditTaskTemplate.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddEditTaskTemplates
 *
 * @brief Handle API requests validation for adding editorial template operations.
 *
 */

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddEditTaskTemplate extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();

        return [
            'stageId' => ['required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'max:255'],
            'include' => ['sometimes', 'boolean'],
            'emailTemplateId' => ['sometimes', 'nullable', 'integer', Rule::exists('email_templates', 'email_id')],
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
        $this->merge([
            'include' => filter_var($this->input('include', false), FILTER_VALIDATE_BOOLEAN),
            'userGroupIds' => array_values(array_map('intval', (array) $this->input('userGroupIds', []))),
        ]);
    }

    public function validated($key = null, $default = null)
    {
        $data = parent::validated();

        return [
            'stageId' => (int) $data['stageId'],
            'title' => $data['title'],
            'include' => $data['include'] ?? false,
            'emailTemplateId' => $data['emailTemplateId'] ?? null,
            'userGroupIds' => $data['userGroupIds'],
        ];
    }
}
