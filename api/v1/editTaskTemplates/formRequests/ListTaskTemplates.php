<?php

namespace PKP\API\v1\editTaskTemplates\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListTaskTemplates extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $contextId = Application::get()->getRequest()->getContext()->getId();
        $stageIds = array_keys(Application::getApplicationStages());
        $emailKeys = Repo::emailTemplate()
            ->getCollector($contextId)
            ->getMany()
            ->map(fn ($t) => $t->getData('key'))
            ->filter()
            ->values()
            ->all();

        return [
            'stageId' => ['sometimes', 'integer', Rule::in($stageIds)],
            'emailTemplateKey' => ['sometimes', 'string', Rule::in($emailKeys)],
            'include' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->query->has('include')) {
            $this->merge([
                'include' => filter_var(
                    $this->query('include'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
            ]);
        }
    }
}
