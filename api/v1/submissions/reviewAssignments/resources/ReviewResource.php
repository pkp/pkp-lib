<?php

/**
 * @file api/v1/submissions/reviewAssignments/resources/ReviewResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewResource
 *
 * @brief Maps a reviewer's review (free-text or custom review form) to the API response.
 *
 * The review form structure is exposed using the same form configuration shape
 * consumed by the front-end FormComponent/Form.vue, while the reviewer's answers
 * are exposed separately as a simple `reviewFormElementId => value` map. This keeps
 * a single, shared configuration-driven form representation and avoids maintaining a
 * competing form format for reviews.
 */

namespace PKP\API\v1\submissions\reviewAssignments\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\components\forms\Field;
use PKP\components\forms\FieldOptions;
use PKP\components\forms\FieldRichTextarea;
use PKP\components\forms\FieldSelect;
use PKP\components\forms\FieldText;
use PKP\core\PKPString;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponseDAO;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\submission\SubmissionCommentDAO;

class ReviewResource extends JsonResource
{
    /** The id used to group the review form fields in the form configuration. */
    public const REVIEW_FORM_GROUP_ID = 'reviewContent';

    /**
     * Transform the review assignment into the review API response.
     */
    public function toArray(Request $request): array
    {
        /** @var ReviewAssignment $reviewAssignment */
        $reviewAssignment = $this->resource;
        $reviewFormId = $reviewAssignment->getReviewFormId();

        $data = [
            'reviewAssignmentId' => $reviewAssignment->getId(),
            'reviewFormId' => $reviewFormId ?: null,
            'reviewerRecommendationId' => $reviewAssignment->getReviewerRecommendationId(),
        ];

        if ($reviewFormId) {
            $data['reviewFormResponses'] = (object)$this->getReviewFormResponses($reviewAssignment);
            $data['reviewFormConfig'] = $this->getReviewFormConfig($reviewFormId);
        } else {
            $data = array_merge($data, $this->getReviewComments($reviewAssignment));
        }

        return $data;
    }

    /**
     * Get the reviewer's answers to a review form. Responses are keyed by ID of the form element that the response corresponds to.
     */
    protected function getReviewFormResponses(ReviewAssignment $reviewAssignment)
    {
        /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
        return  $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
    }

    /**

     */
    protected function getReviewFormConfig(int $reviewFormId): array
    {
        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewFormId);

        $fields = [];
        while ($reviewFormElement = $reviewFormElements->next()) {
            $fields[] = $this->mapReviewFormElementToFormField($reviewFormElement)->getConfig();
        }

        return [
            'id' => self::REVIEW_FORM_GROUP_ID,
            'fields' => $fields,
        ];
    }

    /**
     * Map a review form element to the matching form field component.
     */
    protected function mapReviewFormElementToFormField(ReviewFormElement $reviewFormElement): Field
    {
        $name = (string) $reviewFormElement->getId();
        $args = [
            'label' => PKPString::html2text($reviewFormElement->getLocalizedQuestion()),
            'isRequired' => $reviewFormElement->getRequired(),
            'description' => PKPString::html2text($reviewFormElement->getLocalizedDescription()) ?: '',
        ];

        return match ($reviewFormElement->getElementType()) {
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA => new FieldRichTextarea($name, $args),

            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES => new FieldOptions($name, [
                ...$args,
                'type' => 'checkbox',
                'options' => $this->getReviewFormElementPossibleResponses($reviewFormElement),
            ]),

            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS => new FieldOptions($name, [
                ...$args,
                'type' => 'radio',
                'options' => $this->getReviewFormElementPossibleResponses($reviewFormElement),
            ]),

            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX => new FieldSelect($name, [
                ...$args,
                'options' => $this->getReviewFormElementPossibleResponses($reviewFormElement),
            ]),

            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD =>
            new FieldText($name, $args),
            default => throw new \Exception('ReviewRsource: Unexpected Form Element Type: ' . $reviewFormElement->getElementType()),
        };
    }

    /**
     * Convert a review form element's possible responses into the
     * `{value, label}` option shape used by the form field components.
     */
    protected function getReviewFormElementPossibleResponses(ReviewFormElement $reviewFormElement): array
    {
        $possibleResponses = $reviewFormElement->getLocalizedPossibleResponses();
        if (!is_array($possibleResponses)) {
            return [];
        }

        $options = [];
        foreach ($possibleResponses as $index => $label) {
            $options[] = [
                'value' => $index,
                'label' => $label,
            ];
        }
        return $options;
    }

    /**
     * Get the comment submitted on a review.
     */
    protected function getReviewComments(ReviewAssignment $reviewAssignment): array
    {
        /** @var SubmissionCommentDAO $submissionCommentDao */
        $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
        $comments = $submissionCommentDao->getReviewerCommentsByReviewerId(
            $reviewAssignment->getSubmissionId(),
            $reviewAssignment->getReviewerId(),
            $reviewAssignment->getId(),
        );

        $data = ['comments' => null, 'commentsPrivate' => null];

        /** @var SubmissionComment $comment */
        while ($comment = $comments->next()) {
            $data[$comment->getViewable() ? 'comments' : 'commentsPrivate'] = PKPString::html2text($comment->getComments());
        }
        return $data;
    }
}
