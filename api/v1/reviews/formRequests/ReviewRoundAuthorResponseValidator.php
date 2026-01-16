<?php

namespace PKP\API\v1\reviews\formRequests;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;
use PKP\db\DAORegistry;

trait ReviewRoundAuthorResponseValidator
{
    /*
     * Common validation rules for adding and editing review responses
     */
    protected function commonRules(): array
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $this->reviewRound = $reviewRoundDao->getById($this->route('reviewRoundId'));

        return [
            'reviewRoundId' => [
                'required',
                'integer',
                Rule::exists('review_rounds', 'review_round_id'),
            ],
            'submissionId' => [
                'required',
                'integer',
                Rule::exists('submissions', 'submission_id'),
            ],
            'authorResponse' => [
                'required',
            ],
            'associatedAuthorIds' => [
                'required',
                'array',
            ],
        ];
    }

    /**
     * Perform additional form field specific validations after initial check was passed.
     */
    protected function commonAfter(): array
    {
        return [
            function (Validator $validator) {
                // Only run this validation if all initial checks in `rules` passed
                if (!$validator->errors()->count()) {
                    $publication = Repo::publication()->get($this->reviewRound->getPublicationId());
                    $allAuthors = $publication->getData('authors');
                    $associatedAuthorIds = $this->input('associatedAuthorIds');

                    foreach ($associatedAuthorIds as $authorId) {
                        if (!$allAuthors->get($authorId)) {
                            $validator->errors()->add('associatedAuthorIds', __('api.404.resourceNotFound'));
                            break;
                        }
                    }
                }
            }
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'reviewRoundId' => $this->route('reviewRoundId'),
            'submissionId' => $this->route('submissionId'),
        ]);

        if ($this->route('responseId')) {
            $this->merge([
                'responseId' => $this->route('responseId'),
            ]);
        }
    }


    /**
     * Validation rules and their messages
     */
    protected function commonMessages(): array
    {
        return [
            'submissionId.exists' => __('api.404.resourceNotFound'),
            'reviewRoundId.exists' => __('api.404.resourceNotFound'),
            'authorReviewResponse.required' => __('api.reviewRound.authorResponse.400.missingAuthorReviewResponse'),
            'associatedAuthorIds.required' => __('api.reviewRound.authorResponse.400.missingAuthorIds'),
        ];
    }

    /**
     * Data to be returned after validation
     *
     * @param null|mixed $key
     * @param null|mixed $default
     */
    protected function commonValidated($key = null, $default = null)
    {
        $request = $this->validator->validated();
        $request['associatedAuthorIds'] = $this->input('associatedAuthorIds');
        $request['reviewRound'] = $this->reviewRound;
        $request['authorResponse'] = $this->input('authorResponse');

        return $request;
    }
}
