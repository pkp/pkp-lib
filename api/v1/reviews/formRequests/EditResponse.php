<?php

namespace PKP\API\v1\reviews\formRequests;

use APP\core\Application;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewRound\ReviewAuthorResponse;
use PKP\submission\reviewRound\ReviewRound;

class EditResponse extends FormRequest
{
    use ReviewResponseCommonValidation;

    protected ReviewRound $reviewRound;
    protected ?ReviewAuthorResponse $existingResponse = null;
    public function rules(): array
    {
        $this->existingResponse = ReviewAuthorResponse::withReviewRoundIds([$this->route('reviewRoundId')])
            ->where('response_id', $this->route('responseId'))
            ->first();

        return array_merge(
            [
                'responseId' => [
                    'required',
                    'integer',
                    function (string $attribute, mixed $value, \Closure $fail) {
                        if (!$this->existingResponse || (int) $this->existingResponse->id !== (int) $value) {
                            $fail(__('api.404.resourceNotFound'));
                        }
                    },
                ]
            ],
            $this->commonRules()
        );
    }

    public function after(): array
    {
        return $this->commonAfter();
    }

    /**
     * Further validations not tied to the form data
     */
    protected function passedValidation()
    {
        $request = Application::get()->getRequest();
        $user = $request->getUser();

        $isEditor = StageAssignment::withSubmissionIds([$this->reviewRound->getSubmissionId()])
            ->withRoleIds([Role::ROLE_ID_SUB_EDITOR])
            ->withStageIds([$this->reviewRound->getStageId()])
            ->withUserId($user->getId())
            ->exists();

        // Only assigned editors, managers, or admins are allowed to edit author responses.
        $canEdit = $isEditor ||
            $user->hasRole([Role::ROLE_ID_MANAGER], $request->getContext()->getId()) ||
            $user->hasRole([Role::ROLE_ID_SITE_ADMIN], \PKP\core\PKPApplication::SITE_CONTEXT_ID);


        if (!$canEdit) {
            throw new HttpResponseException(response()->json([
                'error' => __('api.403.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED));
        }
    }


    public function validated($key = null, $default = null)
    {
        $request = $this->commonValidated();
        $request['existingResponse'] = $this->existingResponse;

        return $request;
    }
}
