<?php

/**
 * @file api/v1/publicationPeerReviews/resources/PublicationPeerReviewResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class PublicationPeerReviewResource
 *
 * @ingroup api_v1_publicationPeerReviews
 *
 * @brief Resource that maps a publication to its open peer reviews data
 */

namespace PKP\API\v1\publicationPeerReviews\resources;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Enumerable;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewFormDAO;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;

class PublicationPeerReviewResource extends JsonResource
{
    public function toArray(?\Illuminate\Http\Request $request = null)
    {
        /** @var Publication $publication */
        $publication = $this->resource;
        return [
            'publicationId' => $publication->getId(),
            'reviewRounds' => $this->getPublicationPeerReview($publication)
        ];
    }


    /**
     * Get public peer review data for a publication.
     *
     * @param Publication $publication - The publication to get data for.
     *
     */
    private function getPublicationPeerReview(Publication $publication): Enumerable
    {
        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDao->getByPublicationIds([$publication->getId()]);
        $context = app()->get('context')->get(
            Repo::submission()->get($publication->getData('submissionId'))->getData('contextId')
        );
        $hasMultipleRounds = $reviewRounds->getCount() > 1;
        $roundsData = collect();

        while ($reviewRound = $reviewRounds->next()) {
            $assignments = Repo::reviewAssignment()
                ->getCollector()
                ->filterByReviewRoundIds([$reviewRound->getData('id')])
                ->getMany();

            $roundDisplayText = $hasMultipleRounds ? __('publication.versionStringWithRound', [
                'versionString' => $publication->getData('versionString'),
                'round' => $reviewRound->getData('round')
            ]) :
                $publication->getData('versionString');

            $roundsData->add([
                'displayText' => $roundDisplayText,
                'roundId' => $reviewRound->getData('id'),
                'reviews' => $this->getReviewAssignmentPeerReviews($assignments, $context),
            ]);
        }

        return $roundsData;
    }

    /**
     * Get public peer review specific data for a list of review assignments.
     *
     * @param Enumerable $assignments - The review assignments to get data for.
     * @param Context $context The context the assignments are a part of.
     *
     */
    private function getReviewAssignmentPeerReviews(Enumerable $assignments, Context $context): Enumerable
    {
        return $assignments->map(function (ReviewAssignment $assignment) use ($context) {
            $ReviewForm = null;
            $reviewerComments = null;

            if ($assignment->getReviewFormId()) {
                /** @var ReviewFormDAO $reviewFormDao */
                $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
                $reviewForm = $reviewFormDao->getById($assignment->getReviewFormId(), Application::getContextAssocType(), $context->getId());

                $ReviewForm = [
                    'id' => $reviewForm->getId(),
                    'description' => $reviewForm->getLocalizedDescription(),
                    'title' => $reviewForm->getLocalizedTitle(),
                    'questions' => $this->getReviewFormQuestions($assignment)
                ];
            } else {
                $reviewerComments = $this->getReviewAssignmentComments($assignment);
            }
            return [
                'id' => $assignment->getData('id'),
                'reviewerId' => $assignment->getReviewerId(),
                'reviewerFullName' => $assignment->getReviewerFullName(),
                'reviewerAffiliation' => Repo::user()->get($assignment->getReviewerId())->getLocalizedAffiliation(),
                'dateAssigned' => $assignment->getDateAssigned(),
                'dateConfirmed' => $assignment->getDateConfirmed(),
                'dateCompleted' => $assignment->getDateCompleted(),
                'declined' => $assignment->getDeclined(),
                // Localized text description of the reviewer recommendation(Accept Submission, Decline Submission, etc)
                'reviewerRecommendationDisplayText' => $assignment->getLocalizedRecommendation(),
                'reviewerRecommendationId' => $assignment->getReviewerRecommendationId(),
                'reviewForm' => $ReviewForm,
                'reviewerComments' => $reviewerComments,
            ];
        })->values();
    }

    /**
     * Get all questions and responses from a review form for a given review assignment
     *
     * @param ReviewAssignment $assignment - The review assignment to get responses for.
     *
     */
    private function getReviewFormQuestions(ReviewAssignment $assignment): array
    {
        $formQuestions = [];
        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $reviewFormElements = $reviewFormElementDao->getByReviewFormId($assignment->getReviewFormId());

        /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');

        while ($reviewFormElement = $reviewFormElements->next()) {
            $responses = [];
            $reviewFormResponse = $reviewFormResponseDao->getReviewFormResponse($assignment->getId(), $reviewFormElement->getId());

            // Responses for checkboxes are stored in an array, with each value representing the index of the selected option(s) from the possible responses
            if ($reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
                // convert each index to integer
                $responseIndexesIntegers = array_map('intval', $reviewFormResponse->getValue());
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();

                // For each item in $responseIndexesIntegers, get the value at that index from the possible responses
                foreach ($responseIndexesIntegers as $index) {
                    if (isset($possibleResponses[$index])) {
                        $responses[] = $possibleResponses[$index];
                    }
                }
            } // Else if radio buttons or drop down box, the response is a single index representing the selected option
            elseif (in_array($reviewFormElement->getElementType(), [ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS, ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX])) {
                $selectedIndex = (int)$reviewFormResponse->getValue();
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                if (isset($possibleResponses[$selectedIndex])) {
                    $responses[] = $possibleResponses[$selectedIndex];
                }
            } else {
                // For other types of questions, just return the response value directly
                $responses[] = $reviewFormResponse->getValue();
            }

            $formQuestions[] = [
                'question' => $reviewFormElement->getLocalizedQuestion(),
                'responses' => $responses,
            ];
        }

        return $formQuestions;
    }

    /**
     * Get all comments made by the reviewer for a review assignment
     *
     * @param ReviewAssignment $assignment - The review assignment to get comments for.
     *
     * @throws \Exception
     */
    private function getReviewAssignmentComments(ReviewAssignment $assignment): array
    {
        $reviewerComments = [];
        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');

        $comments = $submissionCommentDao->getReviewerCommentsByReviewerId(
            $assignment->getSubmissionId(),
            $assignment->getReviewerId(),
            $assignment->getId()
        );

        /** @var SubmissionComment $comment */
        while ($comment = $comments->next()) {
            $reviewerComments[] = $comment->getComments();
        }

        return $reviewerComments;
    }
}
