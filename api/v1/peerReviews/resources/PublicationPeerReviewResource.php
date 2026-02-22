<?php

/**
 * @file api/v1/peerReviews/resources/PublicationPeerReviewResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class PublicationPeerReviewResource
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Resource that maps a publication to its open peer reviews data
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use PKP\API\v1\reviews\resources\ReviewRoundAuthorResponseResource;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewForm;
use PKP\reviewForm\ReviewFormDAO;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponse;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;

class PublicationPeerReviewResource extends JsonResource
{
    use ReviewerRecommendationSummary;

    /** @var array<int> Publication IDs claimed by "child" publications pointing to these "source" publications  */
    private array $claimedPublicationIds = [];

    private ?Enumerable $availableReviewerRecommendations = null;

    /** @var Context|null Caches context to avoid redundant fetches */
    private ?Context $context = null;

    /** @var Collection<int, ReviewForm>|null Caches review forms to avoid redundant fetches */
    private ?Collection $reviewFormsCache = null;

    /** @var Collection<int, ReviewFormElement>|null Caches review form elements to avoid redundant fetches */
    private ?Collection $reviewFormElementsCache = null;

    /** @var Collection<int, ReviewFormResponse>|null Caches review form responses to avoid redundant fetches */
    private ?Collection $reviewFormResponsesCache = null;

    /** @var Collection<int, array<string>>|null Caches reviewer comments to avoid redundant fetches */
    private ?Collection $reviewerCommentsCache = null;

    public function toArray(?Request $request = null)
    {
        /** @var Publication $publication */
        $publication = $this->resource;
        $publicationReviewsData = $this->getPublicationPeerReview($publication);
        return [
            'publicationId' => $publication->getId(),
            'datePublished' => $publication->getData('datePublished'),
            'reviewRounds' => $publicationReviewsData->get('roundsData'),
            'reviewerRecommendationsSummary' => $publicationReviewsData->get('reviewerRecommendationsSummary'),
        ];
    }

    /**
     * Fluent method for adding which publication IDs should not be included
     * as they are already considered by the "child" publication.
     *
     * Prevents double review presentation
     *
     * E.g. Publication B has "source" publication A, so publication A can be passed to this method to be excluded
     * when constructing list of peer reviews.
     */
    public function withClaimedPublicationIds(array $publicationIds): static
    {
        $this->claimedPublicationIds = $publicationIds;
        return $this;
    }

    /**
     * Get public peer review data for a publication.
     *
     * @param Publication $publication The publication to get data for.
     *
     */
    private function getPublicationPeerReview(Publication $publication): Collection
    {
        $results = collect();

        // Check up the tree on source IDs
        $allAssociatedPublicationIds = Repo::publication()->getWithSourcePublicationsIds([$publication->getId()]);

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        $reviewRounds = $reviewRoundDao->getByPublicationIds($allAssociatedPublicationIds);

        // Cache context
        if (!$this->context) {
            $this->context = app()->get('context')->get(
                Repo::submission()->get($publication->getData('submissionId'))->getData('contextId')
            );
        }
        $context = $this->context;

        $roundsData = collect();

        $reviewRoundsKeyedById = collect($reviewRounds->toArray())->keyBy(fn ($item) => $item->getId());
        unset($reviewRounds);

        // If this publication is claimed as a source by a child publication, exclude its own review rounds.
        // Those review rounds will appear under the child publication instead,
        // i.e., the "newest," most up-to-date publication.
        if (in_array($publication->getId(), $this->claimedPublicationIds)) {
            $reviewRoundsKeyedById = $reviewRoundsKeyedById->filter(
                fn (ReviewRound $round) => $round->getPublicationId() !== $publication->getId()
            );
        }

        $hasMultipleRounds = $reviewRoundsKeyedById->count() > 1;
        $roundIds = $reviewRoundsKeyedById->keys()->all();

        $reviewAssignments = Repo::reviewAssignment()
            ->getCollector()
            ->filterByReviewRoundIds($roundIds)
            ->filterByIsPubliclyVisible(true)
            ->filterByIsAccepted(true)
            ->getMany();

        $reviewsGroupedByRoundId = $reviewAssignments
            ->groupBy(fn (ReviewAssignment $ra) => $ra->getReviewRoundId())
            ->sortKeys();

        $roundResponses = AuthorResponse::withReviewRoundIds($roundIds)->get()->groupBy('reviewRoundId');

        foreach ($reviewsGroupedByRoundId as $roundId => $assignments) {
            /** @var ReviewRound $reviewRound */
            $reviewRound = $reviewRoundsKeyedById->get($roundId);

            $roundDisplayText = $hasMultipleRounds ? __('publication.versionStringWithRound', [
                'versionString' => $publication->getData('versionString'),
                'round' => $reviewRound->getData('round')
            ]) :
                $publication->getData('versionString');

            /** @var ?AuthorResponse $currentRoundResponse */
            $currentRoundResponse = $roundResponses->get($roundId)?->first();

            $publicStatus = $reviewRound->getPublicReviewStatus($assignments);

            $roundsData->add([
                'displayText' => $roundDisplayText,
                'roundId' => $reviewRound->getData('id'),
                'originalPublicationId' => $reviewRound->getPublicationId(),
                'status' => $publicStatus['status']->value,
                'dateStarted' => $publicStatus['dateStarted'],
                'dateInProgress' => $publicStatus['dateInProgress'],
                'dateCompleted' => $publicStatus['dateCompleted'],
                'reviews' => $this->getReviewAssignmentPeerReviews($assignments, $context)->toArray(),
                'authorResponse' => $currentRoundResponse ? (new ReviewRoundAuthorResponseResource($currentRoundResponse))->resolve() : null,
            ]);
        }


        $results->put('roundsData', $roundsData->toArray());
        $results->put('reviewerRecommendationsSummary', $this->getReviewerRecommendationsSummary($reviewAssignments, $context));
        return $results;
    }

    /**
     * Get public peer review specific data for a list of review assignments.
     *
     * @param Enumerable $assignments The review assignments to get data for.
     * @param Context $context The context the assignments are a part of.
     *
     */
    private function getReviewAssignmentPeerReviews(Enumerable $assignments, Context $context): Enumerable
    {
        $this->availableReviewerRecommendations = $this->availableReviewerRecommendations ?: ReviewerRecommendation::withContextId($context->getId())->get()->keyBy('reviewerRecommendationId');
        $recommendationTypesTypeLabels = Repo::reviewerRecommendation()->getRecommendationTypeLabels();

        // Preload all review form data for use in class
        $this->preloadFormsAndComments($assignments, $context);

        return $assignments->map(function (ReviewAssignment $assignment) use ($recommendationTypesTypeLabels, $context) {
            $reviewForm = null;
            $reviewerComments = null;

            if ($assignment->getReviewFormId()) {
                /** @var ReviewFormDAO $reviewFormDao */
                $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
                $reviewForm = $reviewFormDao->getById($assignment->getReviewFormId(), Application::getContextAssocType(), $context->getId());

                $reviewForm = [
                    'id' => $reviewForm->getId(),
                    'description' => $reviewForm->getLocalizedDescription(),
                    'title' => $reviewForm->getLocalizedTitle(),
                    'questions' => $this->getReviewFormQuestions($assignment)
                ];
            } else {
                $reviewerComments = $this->getReviewAssignmentComments($assignment);
            }

            $isReviewOpen = $assignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN;
            /** @var ReviewerRecommendation $recommendation */
            $recommendation = $this->availableReviewerRecommendations->get($assignment->getReviewerRecommendationId());

            return [
                'id' => $assignment->getData('id'),
                'reviewerId' => $isReviewOpen ? $assignment->getReviewerId() : null,
                'reviewerFullName' => $isReviewOpen ? $assignment->getReviewerFullName() : null,
                'reviewerAffiliation' => $isReviewOpen ? Repo::user()->get($assignment->getReviewerId())->getLocalizedAffiliation() : null,
                'dateCompleted' => $assignment->getDateCompleted(),
                'isReviewOpen' => $isReviewOpen,
                // Localized text description of the reviewer recommendation (Accept Submission, Decline Submission, etc.)
                'reviewerRecommendationDisplayText' => $assignment->getLocalizedRecommendation(),
                'reviewerRecommendationId' => $assignment->getReviewerRecommendationId(),
                // Machine-readable type of the reviewer recommendation (Approved, Not Approved, Revisions Requested, etc.)
                'reviewerRecommendationTypeId' => $recommendation?->type,
                'reviewerRecommendationTypeLabel' => $recommendation ? $recommendationTypesTypeLabels[$recommendation->type] : null,
                'reviewForm' => $reviewForm,
                'reviewerComments' => $reviewerComments,
            ];
        })->values();
    }

    /**
     * Preload all review form data to avoid duplicate or repeat DB calls
     *
     * @param Enumerable<ReviewAssignment> $assignments
     * @throws \Exception
     */
    private function preloadFormsAndComments(Enumerable $assignments, Context $context): void
    {
        /** @var ReviewFormDAO $reviewFormDao */
        $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');

        // Collect unique review form IDs
        $reviewFormIds = $assignments
            ->map(fn (ReviewAssignment $assignment) => $assignment->getReviewFormId())
            ->filter()
            ->unique()
            ->values();

        // Fetch all review forms
        $this->reviewFormsCache = collect();
        foreach ($reviewFormIds as $formId) {
            $form = $reviewFormDao->getById($formId, Application::getContextAssocType(), $context->getId());

            if ($form) {
                $this->reviewFormsCache->put($formId, $form);
            }
        }

        // Fetch all review form elements for each form
        $this->reviewFormElementsCache = collect();
        foreach ($reviewFormIds as $formId) {
            $elements = $reviewFormElementDao->getByReviewFormId($formId);
            $elementsList = collect();

            while ($element = $elements->next()) {
                $elementsList->push($element);
            }

            $this->reviewFormElementsCache->put($formId, $elementsList);
        }

        // Fetch all review form responses for all assignments
        $this->reviewFormResponsesCache = collect();
        foreach ($assignments as $assignment) {
            if ($assignment->getReviewFormId()) {
                $responses = $reviewFormResponseDao->getReviewReviewFormResponseValues($assignment->getId());
                $this->reviewFormResponsesCache->put($assignment->getId(), $responses);
            }
        }

        // Fetch all reviewer comments for assignments without review forms
        $this->reviewerCommentsCache = collect();
        foreach ($assignments as $assignment) {
            if (!$assignment->getReviewFormId()) {
                $comments = $submissionCommentDao->getReviewerCommentsByReviewerId(
                    $assignment->getSubmissionId(),
                    $assignment->getReviewerId(),
                    $assignment->getId(),
                    true
                );

                /** @var Collection<SubmissionComment> $commentsList */
                $commentsList = collect();

                /** @var SubmissionComment $comment */
                while ($comment = $comments->next()) {
                    $commentsList->push($comment->getComments());
                }
                $this->reviewerCommentsCache->put($assignment->getId(), $commentsList->all());
            }
        }
    }

    /**
     * Get all questions and responses from a review form for a given review assignment
     *
     * @param ReviewAssignment $assignment The review assignment to get responses for.
     *
     */
    private function getReviewFormQuestions(ReviewAssignment $assignment): array
    {
        $formQuestions = [];
        $reviewFormId = $assignment->getReviewFormId();

        $reviewForm = $this->reviewFormsCache->get($reviewFormId);
        if (!$reviewForm) {
            return [];
        }

        $reviewFormElements = $this->reviewFormElementsCache->get($reviewFormId, collect());
        $reviewFormResponses = $this->reviewFormResponsesCache->get($assignment->getId(), []);

        foreach ($reviewFormElements as $reviewFormElement) {
            $responses = [];
            $elementId = $reviewFormElement->getId();
            $reviewFormResponse = $reviewFormResponses[$elementId] ?? null;

            if (!$reviewFormResponse) {
                continue;
            }

            // Responses for checkboxes are stored in an array, with each value representing the index of the selected option(s) from the possible responses
            if ($reviewFormElement->getElementType() == ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES) {
                // convert each index to integer
                $responseIndexesIntegers = array_map('intval', $reviewFormResponse);
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();

                // For each item in $responseIndexesIntegers, get the value at that index from the possible responses
                foreach ($responseIndexesIntegers as $index) {
                    if (isset($possibleResponses[$index])) {
                        $responses[] = $possibleResponses[$index];
                    }
                }
            } // Else if radio buttons or drop down box, the response is a single index representing the selected option
            elseif (in_array($reviewFormElement->getElementType(), [ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS, ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX])) {
                $selectedIndex = (int)$reviewFormResponse;
                $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
                if (isset($possibleResponses[$selectedIndex])) {
                    $responses[] = $possibleResponses[$selectedIndex];
                }
            } else {
                // For other types of questions, just return the response value directly
                $responses[] = $reviewFormResponse;
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
     * @param ReviewAssignment $assignment The review assignment to get comments for.
     *
     * @throws \Exception
     */
    private function getReviewAssignmentComments(ReviewAssignment $assignment): array
    {
        return $this->reviewerCommentsCache->get($assignment->getId(), []);
    }
}
