<?php

namespace PKP\API\v1\reviewers\suggestions\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use PKP\validation\traits\HasMultilingualRule;

class AddReviewerSuggestion extends FormRequest
{
    use HasMultilingualRule;

    public function multilingualInputs(): array 
    {
        return [
            'familyName',
            'givenName',
            'affiliation',
            'suggestionReason',
        ];
    }

    public function primaryLocale(): ?string 
    {
        $submission = Repo::submission()->get($this->route('submissionId'));

        return $submission?->getData('locale')
            ?? Application::get()->getRequest()->getContext()->getSupportedDefaultSubmissionLocale();
    }

    public function allowedLocales(): array 
    {
        return Application::get()->getRequest()->getContext()->getSupportedSubmissionLocales();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'submissionId' => [
                'required',
                'integer',
                Rule::exists('submissions', 'submission_id'),
            ],
            'suggestingUserId' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('users', 'user_id'),
            ],
            'familyName' => [
                'required',
            ],
            'givenName' => [
                'required',
            ],
            'email' => [
                'required',
                'email',
            ],
            'affiliation' => [
                'required',
            ],
            'suggestionReason' => [
                'required',
            ],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'familyName' => __('user.familyName'),
            'givenName' => __('user.givenName'),
            'email' => __('user.email'),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'suggestingUserId' => Application::get()->getRequest()?->getUser()?->getId(),
            'submissionId' => $this->route('submissionId'),
        ]);
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    // protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    // {
    //     $formatted = [];
    //     foreach ($validator->errors()->getMessages() as $ruleKey => $messages) {
    //         Arr::set($formatted, $ruleKey, $messages);
    //     }
        
    //     ray($formatted);
    //     parent::failedValidation($validator);
    // }
}
