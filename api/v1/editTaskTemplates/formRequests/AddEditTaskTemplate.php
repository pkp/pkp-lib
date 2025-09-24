<?php

/**
 * @file api/v1/editTaskTemplates/formRequests/AddEditTaskTemplate.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddEditTaskTemplate
 *
 * @brief Handle API requests validation for adding editorial template operations.
 *
 */

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use APP\facades\Repo;
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
        $stageIds  = array_keys(Application::getApplicationStages());
        $emailKeys = Repo::emailTemplate()
            ->getCollector($contextId)
            ->getMany()
            ->map(fn ($t) => $t->getData('key'))
            ->filter()
            ->values()
            ->all();
        return [
            'stageId' => ['required', 'integer', Rule::in($stageIds)],
            'title' => ['required', 'string', 'max:255'],
            'include' => ['boolean'],
            'emailTemplateKey' => ['sometimes', 'nullable', 'string', 'max:255', Rule::in($emailKeys)],
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
        $this->merge([
            'include' => filter_var($this->input('include', false), FILTER_VALIDATE_BOOLEAN),
            'userGroupIds' => array_values(array_map('intval', (array) $this->input('userGroupIds', []))),
            'stageId' => is_null($stageId) ? $stageId : (int) $stageId,
        ]);
    }

    protected function passedValidation(): void
    {
        $key = $this->input('emailTemplateKey');
        if (is_string($key)) {
            $this->merge(['emailTemplateKey' => trim($key)]);
        }
    }
}
