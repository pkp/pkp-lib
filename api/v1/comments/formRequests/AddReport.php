<?php

/**
 * @file api/v1/comments/formRequests/AddReport.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AddReport
 *
 * @brief Handle API requests validation for adding comment reports.
 */

namespace PKP\API\v1\comments\formRequests;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use PKP\core\PKPString;
use PKP\userComment\UserComment;

class AddReport extends FormRequest
{
    /**
     * The validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'note' => [
                'required',
                'string',
            ],
        ];
    }

    /**
     * Get the custom error messages for the validation rules.
     */
    public function messages(): array
    {
        return [
            'note.required' => __('api.userComments.form.400.required.note'),
        ];
    }

    /**
     * Handle a passed validation attempt.
     */
    protected function passedValidation(): void
    {
        $commentId = $this->route('commentId');
        $comment = UserComment::query()->find($commentId);

        if (!$comment) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.404.resourceNotFound'),
            ], Response::HTTP_NOT_FOUND));
        }

        $user = Application::get()->getRequest()->getUser();

        if (!Repo::userComment()->isModerator($user) && !$comment->isApproved) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_FORBIDDEN));
        }
    }

    /**
     * Override the default `failedValidation` method to return a JSON response consistent with existing error responses in codebase.
     */
    public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json($validator->errors(), Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    /** @inheritdoc */
    public function validated($key = null, $default = null)
    {
        $request = $this->validator->validated();
        $request['note'] = PKPString::stripUnsafeHtml($this->input('note'));
        return $request;
    }
}
