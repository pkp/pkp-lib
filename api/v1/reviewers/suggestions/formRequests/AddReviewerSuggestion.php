<?php

namespace PKP\API\v1\reviewers\suggestions\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddReviewerSuggestion extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @copydoc \Illuminate\Foundation\Http\FormRequest::validated()
     */
    public function validated($key = null, $default = null)
    {
        $validated = parent::validated($key, $default);

        return collect($validated)->map(
            fn($value) => is_array($value) ? array_filter($value) : $value
        )->toArray();
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
                'array',
            ],
            'givenName' => [
                'required',
                'array',
            ],
            'email' => [
                'required',
                'email',
            ],
            'affiliation' => [
                'required',
                'array',
            ],
            'suggestionReason' => [
                'required',
                'array',
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'familyName.required' => 'family name is required',
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
}
