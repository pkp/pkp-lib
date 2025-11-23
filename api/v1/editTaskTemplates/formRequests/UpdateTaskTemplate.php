<?php

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use PKP\editorialTask\enums\EditorialTaskDueInterval;
use PKP\editorialTask\enums\EditorialTaskType;

class UpdateTaskTemplate extends FormRequest
{
    use TaskTemplateRequestTrait;

    public function rules(): array
    {
        $contextId = $this->getContextId();
        $stageIds = $this->getStageIds();

        return [
            'stageId' => ['sometimes', 'integer', Rule::in($stageIds)],
            'title' => ['sometimes', 'string', 'max:255'],
            'include' => ['sometimes', 'boolean'],
            'description' => ['sometimes', 'nullable', 'string'],
            'dueInterval' => ['sometimes', 'nullable', 'string', Rule::in(array_column(EditorialTaskDueInterval::cases(), 'value'))],
            'type' => ['sometimes', Rule::in(array_column(EditorialTaskType::cases(), 'value'))],

            'userGroupIds' => ['sometimes', 'array', 'min:1'],
            'userGroupIds.*' => $this->userGroupIdsItemRules($contextId),
        ];
    }

    protected function prepareForValidation(): void
    {
        $stageId = $this->input('stageId');
        $type = $this->input('type');

        $data = [];

        if ($this->has('include')) {
            $data['include'] = filter_var($this->input('include'), FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('userGroupIds')) {
            $data['userGroupIds'] = array_values(
                array_map('intval', (array) $this->input('userGroupIds', []))
            );
        }

        if ($this->has('stageId')) {
            $data['stageId'] = is_null($stageId) ? null : (int) $stageId;
        }

        if ($this->has('type')) {
            $data['type'] = is_null($type) ? null : (int) $type;
        }

        if ($data) {
            $this->merge($data);
        }
    }

}
