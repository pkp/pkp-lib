<?php

namespace PKP\mail\traits;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\PKPApplication;

trait ReviewRoundAuthorResponse
{
    protected static string $reviewRoundAuthorResponseUrl = 'reviewRoundAuthorResponseUrl';

    protected function setupReviewAuthorResponseVariables(Submission $submission, int $reviewRoundId, int $stageId, Context $context): void
    {
        $request = PKPApplication::get()->getRequest();
        $url = $request->getDispatcher()->url(
            $request,
            PKPApplication::ROUTE_PAGE,
            $context->getData('urlPath'),
            'dashboard',
            'mySubmissions',
            null,
            [
                'workflowSubmissionId' => $submission->getId(),
                // Trigger dashboard UI to open submission in Review workflow with specific round selected
                'workflowMenuKey' => "workflow_{$stageId}_{$reviewRoundId}",
                // Trigger UI to open the Author Response form modal
                'reviewResponseAction' => 'respond'
            ]
        );
        $this->addData([static::$reviewRoundAuthorResponseUrl => $url]);
    }

    /**
     * Add the author review response variables to the list of registered variables.
     */
    protected static function addReviewAuthorResponseDataDescriptions(array $variables): array
    {
        $variables[static::$reviewRoundAuthorResponseUrl] = __('emailTemplate.variable.reviewRoundAuthorResponseUrl');
        return $variables;
    }
}
