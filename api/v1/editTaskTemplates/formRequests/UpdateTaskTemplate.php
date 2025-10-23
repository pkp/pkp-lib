<?php

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $stageIds = array_values(array_unique(array_filter(
            array_map('intval', array_merge(array_keys((array)$stages), array_values((array)$stages))),
            fn ($id) => $id > 0
        )));

        // Context-scoped email keys
        $emailKeys = \APP\facades\Repo::emailTemplate()
            ->getCollector($contextId)
            ->getMany()
            ->map(fn ($t) => $t->getData('key'))
            ->filter()
            ->values()
            ->all();

        return [
            // all fields are optional but if present must validate
            'stageId' => ['sometimes', 'integer', Rule::in($stageIds)],
            'title' => ['sometimes', 'string', 'max:255'],
            'include' => ['sometimes', 'boolean'],
            'emailTemplateKey' => ['sometimes', 'nullable', 'string', 'max:255', Rule::in($emailKeys)],
            'type' => ['sometimes','integer','in:1,2'],

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

    protected function passedValidation(): void
    {
        if ($this->has('emailTemplateKey')) {
            $key = $this->input('emailTemplateKey');
            $this->merge(['emailTemplateKey' => is_string($key) ? trim($key) : $key]);
        }
    }
}
