<?php

/**
 * @file api/v1/reviews/resources/ReviewFormResource.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING
 *
 * @class ReviewFormResource
 *
 * @ingroup api_v1_reviews
 *
 * @brief Serializes a review form together with its elements as a single,
 *   self-contained object. Each element optionally carries the reviewer's own
 *   raw saved response (see withResponses()), so the shape is reusable both for
 *   editing a review and for rendering a blank form.
 */

namespace PKP\API\v1\reviews\resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PKP\db\DAORegistry;
use PKP\reviewForm\ReviewForm;
use PKP\reviewForm\ReviewFormElement;
use PKP\reviewForm\ReviewFormElementDAO;
use PKP\reviewForm\ReviewFormResponseDAO;

class ReviewFormResource extends JsonResource
{
    /** @var array<int, mixed>|null Reviewer's saved responses, keyed by element id */
    private ?array $responses = null;

    /**
     * Attach the reviewer's saved responses - without this the form serializes blank
     */
    public function withResponses(int $reviewAssignmentId): static
    {
        /** @var ReviewFormResponseDAO $reviewFormResponseDao */
        $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
        $this->responses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignmentId);
        return $this;
    }

    /**
     * @copydoc \Illuminate\Http\Resources\Json\JsonResource::toArray()
     */
    public function toArray(?Request $request = null): array
    {
        /** @var ReviewForm $reviewForm */
        $reviewForm = $this->resource;

        return [
            'id' => $reviewForm->getId(),
            'title' => $reviewForm->getLocalizedTitle(),
            'description' => $reviewForm->getLocalizedDescription(),
            'elements' => $this->mapElements($reviewForm->getId()),
        ];
    }

    /**
     * Map the form's elements, each with the reviewer's own response value
     *
     * @return array<int, array<string, mixed>>
     */
    private function mapElements(int $reviewFormId): array
    {
        /** @var ReviewFormElementDAO $reviewFormElementDao */
        $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
        $elementsIterator = $reviewFormElementDao->getByReviewFormId($reviewFormId);

        $elements = [];
        while ($element = $elementsIterator->next()) {
            $elementData = [
                'id' => $element->getId(),
                'elementType' => $element->getElementType(),
                'question' => strip_tags($element->getLocalizedQuestion()),
                'description' => strip_tags($element->getLocalizedDescription()),
                'required' => (bool) $element->getRequired(),
                'sequence' => $element->getSequence(),
                // The raw answer - a string, an option index, or an array of indices
                'value' => $this->responses[$element->getId()] ?? null,
            ];

            // Only choice-based elements have options to pick from
            if (in_array($element->getElementType(), [
                ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES,
                ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS,
                ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX,
            ])) {
                $elementData['possibleResponses'] = $element->getLocalizedPossibleResponses();
            }

            $elements[] = $elementData;
        }

        return $elements;
    }
}
