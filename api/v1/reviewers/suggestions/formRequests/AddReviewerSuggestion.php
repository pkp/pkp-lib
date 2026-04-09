<?php

/**
 * @file api/v1/reviewers/suggestions/formRequests/AddReviewerSuggestion.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddReviewerSuggestion
 *
 * @brief Handle API requests validation for adding reviewer suggestion operations.
 *
 */

namespace PKP\API\v1\reviewers\suggestions\formRequests;

use APP\core\Application;
use Illuminate\Database\Query\Builder;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use PKP\submission\reviewer\suggestion\ReviewerSuggestion;
use PKP\validation\traits\HasMultilingualRule;

class AddReviewerSuggestion extends FormRequest
{
    use HasMultilingualRule;

    /**
     * @copydoc \PKP\validation\traits\HasMultilingualRule::multilingualInputs()
     */
    public function multilingualInputs(): array 
    {
        return (new ReviewerSuggestion)->getMultilingualProps();
    }

    /**
     * @copydoc \PKP\validation\traits\HasMultilingualRule::primaryLocale()
     */
    public function primaryLocale(): ?string
    {
        return Application::get()->getRequest()->getContext()->getPrimaryLocale();
    }

    /**
     * @copydoc \PKP\validation\traits\HasMultilingualRule::allowedLocales()
     */
    public function allowedLocales(): array
    {
        $allowedLocales = Application::get()->getRequest()->getContext()->getSupportedFormLocales();
        $sitePrimaryLocale = Application::get()->getRequest()->getSite()->getPrimaryLocale();
        if (!in_array($sitePrimaryLocale, $allowedLocales)) {
            $allowedLocales[] = $sitePrimaryLocale;
        }
        return $allowedLocales;
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
                'sometimes',
            ],
            'givenName' => [
                'required',
            ],
            'email' => [
                'required',
                'email',
                Rule::unique((new ReviewerSuggestion)->getTable())
                    ->where(fn (Builder $query) => $query->where('submission_id', [$this->route('submissionId')])),
            ],
            'affiliation' => [
                'required',
            ],
            'suggestionReason' => [
                'required',
            ],
            'orcidId' => [
                'sometimes',
                'nullable',
                'string',
                'orcid',
            ],
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
