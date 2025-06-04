<?php

/**
 * @file api/v1/comments/formRequests/AddComment.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddComment
 *
 * @brief Handle API requests validation for adding comments.
 */

namespace PKP\API\v1\comments\formRequests;

use APP\facades\Repo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use PKP\core\PKPString;

class AddComment extends FormRequest
{
    /**
     * The validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'publicationId' => [
                'required',
                'integer',
                Rule::exists('publications', 'publication_id'),
            ],
            'commentText' => [
                'required',
                'string',
            ],
        ];
    }


    /**
     * Get the validation rules that apply after the initial validation.
     *
     * This method is used to perform additional checks that depend on the
     * results of the initial validation, such as checking if a publication
     * is the current version of a submission.
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Only run this validation if all initial checks in `rules` passed
                if (!$validator->errors()->count()) {
                    $publicationId = (int)$this->input('publicationId');
                    $publication = Repo::publication()->get($publicationId);
                    $submissionId = $publication->getData('submissionId');
                    $submission = Repo::submission()->get((int)$submissionId);

                    if (!$submission || $submission->getCurrentPublication()->getId() !== $publicationId) {
                        $validator->errors()->add('publicationId', __('api.userComments.400.cannotCommentOnPublicationVersion'));
                    }
                }
            },
        ];
    }

    /**
     * Get the custom error messages for the validation rules.
     */
    public function messages(): array
    {
        return [
            'publicationId.required' => __('api.userComments.form.400.required.publicationId'),
            'publicationId.integer' => __('api.userComments.400.invalidPublicationId', ['publicationId' => $this->input('publicationId')]),
            'publicationId.exists' => __('api.404.resourceNotFound'),
            'commentText.required' => __('api.userComments.form.400.required.commentText'),
        ];
    }

    /**
     * Override the default `failedValidation` method to return a JSON response consistent with existing error responses in codebase.
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /** @inheritdoc  */
    public function validated($key = null, $default = null)
    {
        $request = $this->validator->validated();
        $request['commentText'] = PKPString::stripUnsafeHtml($this->input('commentText'));
        return $request;
    }
}
