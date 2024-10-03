<?php

namespace PKP\API\v1\reviewerSuggestions\formRequests;

use Illuminate\Foundation\Http\FormRequest;

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
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'familyName' => [
                'required',
                'string',
                'max:255',
            ],
            'givenName' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email',
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
}
