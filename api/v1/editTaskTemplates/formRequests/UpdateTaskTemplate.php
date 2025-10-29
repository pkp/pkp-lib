<?php

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PKP\editorialTask\enums\EditorialTaskDueInterval;
use PKP\editorialTask\enums\EditorialTaskType;

class UpdateTaskTemplate extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();

        // Build allowed stage IDs (values)
        $stages = Application::getApplicationStages();
        $stageIds = array_values(array_unique(array_map('intval', array_values((array) $stages))));

        return [
            // all fields are optional but if present must validate
            'stageId' => ['sometimes', 'integer', Rule::in($stageIds)],
            'title' => ['sometimes', 'string', 'max:255'],
            'include' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'dueInterval' => ['sometimes', 'nullable', 'string',
                Rule::in(array_column(EditorialTaskDueInterval::cases(), 'value'))
            ],
            'type' => ['sometimes', Rule::in(array_column(EditorialTaskType::cases(), 'value'))],

            'userGroupIds' => ['sometimes', 'array', 'min:1'],
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
            'include' => $this->has('include')
                ? filter_var($this->input('include'), FILTER_VALIDATE_BOOLEAN)
                : $this->input('include', null),

            'userGroupIds' => $this->has('userGroupIds')
                ? array_values(array_map('intval', (array) $this->input('userGroupIds', [])))
                : $this->input('userGroupIds', null),

            'stageId' => $this->has('stageId')
                ? (is_null($stageId) ? null : (int) $stageId)
                : $this->input('stageId', null),

            'type' => $this->has('type')
                ? (is_null($type) ? null : (int) $type)
                : $this->input('type', null),
        ]);
    }

}
