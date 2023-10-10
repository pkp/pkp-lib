<?php

/**
 * @file classes/mail/traits/ReviewerComments.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerComments
 *
 * @ingroup mail_traits
 *
 * @brief Mailable trait to add reviewer comments to a mailable's variables
 */

namespace PKP\mail\traits;

use APP\submission\Submission;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponse;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;

trait ReviewerComments
{
    protected static string $allReviewerComments = 'allReviewerComments';

    /**
     * Add the reviewer comments to the list of registered variables
     */
    protected static function addReviewerCommentsDescription(array $variables): array
    {
        $variables[static::$allReviewerComments] = __('emailTemplate.variable.allReviewersComments');
        return $variables;
    }

    /**
     * Add a variable with comments from all completed review assignments
     *
     * @param array<ReviewAssignment> $reviewAssignments
     */
    protected function setupReviewerCommentsVariable(array $reviewAssignments, Submission $submission)
    {
        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');

        $reviewerNumber = 0;
        $comments = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            $reviewerNumber++;

            $submissionComments = $submissionCommentDao->getReviewerCommentsByReviewerId(
                $submission->getId(),
                $reviewAssignment->getReviewerId(),
                $reviewAssignment->getId(),
                true
            );

            $reviewerIdentity = $reviewAssignment->getReviewMethod() == ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN
                ? $reviewAssignment->getReviewerFullName()
                : __('submission.comments.importPeerReviews.reviewerLetter', ['reviewerLetter' => $reviewerNumber]);
            $recommendation = $reviewAssignment->getLocalizedRecommendation();

            $commentsBody = '';
            /** @var SubmissionComment $comment */
            while ($comment = $submissionComments->next()) {
                // If the comment is viewable by the author, then add the comment.
                if ($comment->getViewable()) {
                    $commentsBody .= PKPString::stripUnsafeHtml($comment->getComments());
                }
            }

            $comments[] =
                '<p>'
                . '<strong>' . $reviewerIdentity . '</strong>'
                . '<br>'
                . __('submission.recommendation', ['recommendation' => $recommendation])
                . '</p>'
                . $commentsBody
                . $this->getReviewFormComments($reviewAssignment);
        }

        $this->addData([
            static::$allReviewerComments => join('', $comments),
        ]);
    }

    protected function getReviewFormComments(ReviewAssignment $reviewAssignment): string
    {
        if (!$reviewAssignment->getReviewFormId()) {
            return '';
        }

        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());

        if ($reviewFormElements->wasEmpty()) {
            return '';
        }

        /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');

        $comments = '';
        while ($reviewFormElement = $reviewFormElements->next()) {
            if (!$reviewFormElement->getIncluded()) {
                continue;
            }

            /** @var ReviewFormResponse|null $reviewFormResponse */
            $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($reviewAssignment->getId(), $reviewFormElement->getId());
            if (!$reviewFormResponse) {
                continue;
            }
            $comments .= PKPString::stripUnsafeHtml($reviewFormElement->getLocalizedQuestion());
            $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
            // See issue #2437.
            if (in_array($reviewFormElement->getElementType(), [$reviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES, $reviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS])) {
                ksort($possibleResponses);
                $possibleResponses = array_values($possibleResponses);
            }
            if (in_array($reviewFormElement->getElementType(), $reviewFormElement->getMultipleResponsesElementTypes())) {
                if ($reviewFormElement->getElementType() == $reviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
                    $comments .= '<ul>';
                    foreach ($reviewFormResponse->getValue() as $value) {
                        $comments .= '<li>' . PKPString::stripUnsafeHtml($possibleResponses[$value]) . '</li>';
                    }
                    $comments .= '</ul>';
                } else {
                    $comments .= '<p>' . PKPString::stripUnsafeHtml($possibleResponses[$reviewFormResponse->getValue()]) . '</p>';
                }
            } else {
                $comments .= '<p>' . nl2br(htmlspecialchars($reviewFormResponse->getValue())) . '</p>';
            }
        }

        return $comments;
    }
}
