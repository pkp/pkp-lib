<?php

namespace PKP\mail\traits;

use APP\submission\Submission;
use PKP\context\Context;
use PKP\core\PKPApplication;

trait AuthorReviewResponseVariables
{
    protected static string $authorReviewResponseUrl = 'authorReviewResponseUrl';

    protected function setupAuthorReviewResponseVariables(Submission $submission, int $reviewRoundId, int $stageId, Context $context): void
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
                // Trigger UI to open submission in Review workflow with specific round selected
                'workflowMenuKey' => "workflow_{$stageId}_{$reviewRoundId}",
                'reviewResponseAction' => 'respond'
            ]
        );
        $this->addData([static::$authorReviewResponseUrl => $url]);
    }

    /**
     * Add the author review response variables to the list of registered variables.
     */
    protected static function addAuthorReviewResponseDataDescriptions(array $variables): array
    {
        $variables[static::$authorReviewResponseUrl] = __('emailTemplate.variable.authorReviewResponseUrl');
        return $variables;
    }
}
