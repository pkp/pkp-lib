<?php

/**
 * @file api/v1/peerReviews/resources/SubmissionPeerReviewResource.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class SubmissionPeerReviewResource
 *
 * @ingroup api_v1_peerReviews
 *
 * @brief Resource that maps a submission to its open peer review record: a flat,
 *  chronological list of review rounds, each carrying the publication version it reviewed.
 */

namespace PKP\API\v1\peerReviews\resources;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use APP\submission\Submission;
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

class SubmissionPeerReviewResource extends JsonResource
{
    use ReviewerRecommendationSummary;

    private ?Enumerable $availableReviewerRecommendations = null;

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
        /** @var Submission $submission */
        $submission = $this->resource;

        $contextDao = Application::getContextDAO();
        /** @var Context $context */
        $context = $contextDao->getById($submission->getData('contextId'));

        /** @var Collection<int, Publication> $publishedPublications */
        $publishedPublications = collect($submission->getPublishedPublications())
            ->keyBy(fn (Publication $publication) => $publication->getId());

        /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
        // Only rounds whose reviewed publication version is published are part of the
        // public record; rounds of an unpublished (in-review) version stay hidden.
        $reviewRounds = collect($reviewRoundDao->getBySubmissionId($submission->getId())->toArray())
            ->filter(fn (ReviewRound $reviewRound) => $reviewRound->getPublicationId() !== null
                && $publishedPublications->has((int) $reviewRound->getPublicationId()));

        $roundIds = $reviewRounds->map(fn (ReviewRound $reviewRound) => $reviewRound->getId())->values()->all();

        $reviewAssignments = empty($roundIds) ? collect() : Repo::reviewAssignment()
            ->getCollector()
            ->filterByReviewRoundIds($roundIds)
            ->filterByIsPubliclyVisible(true)
            ->filterByIsAccepted(true)
            ->filterByIsConfirmedByEditor(true)
            ->getMany()
            // Materialize the lazy collection: it is iterated once per consumer
            // below and each LazyCollection iteration re-runs the query and
            // re-hydrates every assignment
            ->collect();

        $reviewsGroupedByRoundId = $reviewAssignments
            ->groupBy(fn (ReviewAssignment $reviewAssignment) => $reviewAssignment->getReviewRoundId());

        $roundResponses = empty($roundIds)
            ? collect()
            : AuthorResponse::withReviewRoundIds($roundIds)->get()->groupBy('reviewRoundId');

        $roundsData = collect();
        $roundNumber = 0;

        /** @var ReviewRound $reviewRound */
        foreach ($reviewRounds as $reviewRound) {
            /** @var ?Enumerable $assignments */
            $assignments = $reviewsGroupedByRoundId->get($reviewRound->getId());

            // Rounds without any publicly visible review are not part of the public record
            if (!$assignments || $assignments->isEmpty()) {
                continue;
            }

            $roundNumber++;

            /** @var Publication $publication */
            $publication = $publishedPublications->get((int) $reviewRound->getPublicationId());

            /** @var ?AuthorResponse $currentRoundResponse */
            $currentRoundResponse = $roundResponses->get($reviewRound->getId())?->first();

            $reviewStatusData = $reviewRound->getPublicReviewStatusByAssignments($assignments);

            $roundsData->add([
                'roundId' => $reviewRound->getId(),
                'roundNumber' => $roundNumber,
                'publication' => [
                    'id' => $publication->getId(),
                    'versionString' => $publication->getData('versionString'),
                    'versionStage' => $publication->getData('versionStage'),
                    'datePublished' => $publication->getData('datePublished'),
                ],
                ...$reviewStatusData->toArray(),
                'reviews' => $this->getReviewAssignmentPeerReviews($assignments, $context)->toArray(),
                'authorResponse' => $currentRoundResponse ? (new ReviewRoundAuthorResponseResource($currentRoundResponse))->resolve() : null,
            ]);
        }

        return [
            'submissionId' => $submission->getId(),
            'reviewRounds' => $roundsData->toArray(),
            'reviewerRecommendationsSummary' => $this->getReviewerRecommendationsSummary($reviewAssignments, $context),
        ];
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

        return $assignments->map(function (ReviewAssignment $assignment) use ($recommendationTypesTypeLabels) {
            $reviewForm = null;
            $reviewerComments = null;

            if ($assignment->getReviewFormId()) {
                $reviewFormData = $this->reviewFormsCache->get($assignment->getReviewFormId());

                $reviewForm = $reviewFormData ? [
                    'id' => $reviewFormData->getId(),
                    'description' => $reviewFormData->getLocalizedDescription(),
                    'title' => $reviewFormData->getLocalizedTitle(),
                    'questions' => $this->getReviewFormQuestions($assignment)
                ] : null;
            } else {
                $reviewerComments = $this->getReviewAssignmentComments($assignment);
            }

            $isReviewOpen = $assignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN;
            $reviewer = $isReviewOpen ? Repo::user()->get($assignment->getReviewerId()) : null;
            /** @var ReviewerRecommendation $recommendation */
            $recommendation = $this->availableReviewerRecommendations->get($assignment->getReviewerRecommendationId());

            return [
                'id' => $assignment->getData('id'),
                'reviewerId' => $isReviewOpen ? $assignment->getReviewerId() : null,
                'reviewerFullName' => $isReviewOpen ? $assignment->getReviewerFullName() : null,
                'reviewerAffiliation' => $isReviewOpen ? $reviewer?->getLocalizedAffiliation() : null,
                'reviewerOrcid' => $isReviewOpen ? $reviewer?->getOrcid() : null,
                'reviewerHasVerifiedOrcid' => $isReviewOpen ? (bool) $reviewer?->hasVerifiedOrcid() : false,
                'dateCompleted' => $assignment->getDateCompleted(),
                'isReviewOpen' => $isReviewOpen,
                // Localized text description of the reviewer recommendation (Accept Submission, Decline Submission, etc.)
                'reviewerRecommendationDisplayText' => $assignment->getLocalizedRecommendation($context),
                'reviewerRecommendationId' => $assignment->getReviewerRecommendationId(),
                // Machine-readable type of the reviewer recommendation (Approved, Not Approved, Revisions Requested, etc.)
                'reviewerRecommendationTypeId' => $recommendation?->type,
                'reviewerRecommendationTypeLabel' => $recommendation ? $recommendationTypesTypeLabels[$recommendation->type] : null,
                'reviewForm' => $reviewForm,
                'reviewerComments' => $reviewerComments,
                // Withheld (null) rather than false for non-open reviews: the reviewer may
                // have declared, but a free-text statement could deanonymize them.
                'competingInterestsDeclared' => $isReviewOpen ? $assignment->getCompetingInterestsDeclared() : null,
                'competingInterests' => $isReviewOpen ? $assignment->getCompetingInterests() : null,
            ];
        })->values();
    }

    /**
     * Preload all review form data to avoid duplicate or repeat DB calls
     *
     * @param Enumerable<ReviewAssignment> $assignments
     *
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
        $this->reviewFormsCache = $this->reviewFormsCache ?? collect();
        foreach ($reviewFormIds as $formId) {
            if ($this->reviewFormsCache->has($formId)) {
                continue;
            }

            $form = $reviewFormDao->getById($formId, Application::getContextAssocType(), $context->getId());

            if ($form) {
                $this->reviewFormsCache->put($formId, $form);
            }
        }

        // Fetch all review form elements for each form
        $this->reviewFormElementsCache = $this->reviewFormElementsCache ?? collect();
        foreach ($reviewFormIds as $formId) {
            if ($this->reviewFormElementsCache->has($formId)) {
                continue;
            }

            $elements = $reviewFormElementDao->getByReviewFormId($formId);
            $elementsList = collect();

            while ($element = $elements->next()) {
                $elementsList->push($element);
            }

            $this->reviewFormElementsCache->put($formId, $elementsList);
        }

        // Fetch all review form responses for all assignments
        $this->reviewFormResponsesCache = $this->reviewFormResponsesCache ?? collect();
        foreach ($assignments as $assignment) {
            if ($assignment->getReviewFormId() && !$this->reviewFormResponsesCache->has($assignment->getId())) {
                $responses = $reviewFormResponseDao->getReviewReviewFormResponseValues($assignment->getId());
                $this->reviewFormResponsesCache->put($assignment->getId(), $responses);
            }
        }

        // Fetch all reviewer comments for assignments without review forms
        $this->reviewerCommentsCache = $this->reviewerCommentsCache ?? collect();
        foreach ($assignments as $assignment) {
            if (!$assignment->getReviewFormId() && !$this->reviewerCommentsCache->has($assignment->getId())) {
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
