<?php

namespace PKP\API\v1\reviewers\suggestions\formRequests;

use Illuminate\Http\Response;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use PKP\submission\reviewer\suggestion\ReviewerSuggestion;

class EditReviewerSuggestion extends FormRequest
{
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
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        if (!ReviewerSuggestion::find($this->route('suggestionId'))) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND));
        }
    }
}
