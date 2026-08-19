<?php

/**
 * @file controllers/grid/eventLog/SubmissionReviewEventLogGridHandler.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SubmissionReviewEventLogGridHandler
 *
 * @brief Handle submission logs directly related to reviews.
 */

namespace PKP\controllers\grid\eventLog;

use APP\facades\Repo;
use Exception;
use PKP\core\JSONMessage;
use PKP\core\PKPString;
use PKP\log\event\EventLogEntry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\reviewForm\ReviewFormElement;
use PKP\security\Role;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;

class SubmissionReviewEventLogGridHandler extends SubmissionEventLogGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_SUB_EDITOR],
            ['viewReviewChange']
        );
    }

    /**
     * Get the contents of the log.
     */
    public function viewReviewChange($args): JSONMessage
    {
        $logEntry = Repo::eventLog()->get((int)$args['logEntryId']);
        return new JSONMessage(true, $this->formatReviewChange($logEntry));
    }

    /**
     * Build the HTML to display info in the activity log.
     */
    protected function formatReviewChange(EventLogEntry $logEntry): string
    {
        $results = '';
        if ($logEntry->getEventType() === PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_RECOMMENDATION_MODIFIED) {
            $results = '<form class="pkp_form">' . $this->formatReviewerRecommendationChange($logEntry) . '</form>';
        } elseif ($logEntry->getEventType() === PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_COMMENTS_MODIFIED) {
            $results = '<form class="pkp_form">' . $this->formatCommentsChange($logEntry) . '</form>';
        } elseif ($logEntry->getEventType() === PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_REVIEWER_FORM_RESPONSE_MODIFIED) {
            $results = $this->formatReviewFormResponseChange($logEntry);
        }

        return $results;
    }

    /**
     * Render a new value / previous value pair.
     */
    protected function formatBasicValueChange(string $previous, string $new, string $newValueLabel, string $previousValueLabel): string
    {
        return '<div><h3>' . $newValueLabel . '</h3><div><p>' . $new . '</p></div></div>'
            . '<div><h3>' . $previousValueLabel . '</h3> <div><p>' . $previous . '</p></div></div>';
    }

    /**
     * Format details about the reviewer recommendation change.
     */
    protected function formatReviewerRecommendationChange(EventLogEntry $logEntry): string
    {
        return $this->formatBasicValueChange(
            $this->getReviewerRecommendationLabel($logEntry->getData('reviewerRecommendationOldId')),
            $this->getReviewerRecommendationLabel($logEntry->getData('reviewerRecommendationNewId')),
            __('submission.event.review.updatedReviewerRecommendation'),
            __('submission.event.review.previousReviewerRecommendation')
        );
    }

    /**
     * Get the reviewer recommendation label.
     */
    protected function getReviewerRecommendationLabel(?int $recommendationId): string
    {
        if (!$recommendationId) {
            return '-';
        }

        $recommendation = ReviewerRecommendation::find($recommendationId);
        return $recommendation->getLocalizedData('title');
    }

    /**
     * Format contents of the review comments change.
     */
    protected function formatCommentsChange(EventLogEntry $logEntry): string
    {
        $previous = (string)$logEntry->getData('reviewerCommentsOld');
        $new = (string)$logEntry->getData('reviewerCommentsNew');

        return $this->formatBasicValueChange(
            PKPString::stripUnsafeHtml($previous),
            PKPString::stripUnsafeHtml($new),
            __('submission.event.review.updatedComments'),
            __('submission.event.review.previousComments')
        );
    }

    /**
     * Format contents of the review form response change.
     */
    protected function formatReviewFormResponseChange(EventLogEntry $logEntry): string
    {
        $previousResponses = json_decode((string)$logEntry->getData('reviewFormResponseOld'), true) ?: [];
        $newResponses = json_decode((string)$logEntry->getData('reviewFormResponseNew'), true) ?: [];

        return '<div>'
            . '<h1 class="underline text-3xl-bold uppercase text-heading">' . __('submission.event.review.updatedFormResponses') . '</h1>'
            . '<form class="pkp_form">'
            . $this->formatReviewFormResponses($newResponses)
            . '</form></div>'

            . '<div class="mt-6">'
            . '<h1 class="underline text-3xl-bold uppercase text-heading">' . __('submission.event.review.previousFormResponses') . '</h1>'
            . '<form class="pkp_form">'
            . $this->formatReviewFormResponses($previousResponses)
            . '</form>
              </div>';
    }

    /**
     * Format each element in the review form with its question and response
     */
    protected function formatReviewFormResponses(array $responses): string
    {
        $sections = [];
        foreach ($responses as $response) {
            $question = PKPString::html2text($response['question']);

            // When the form element offers a predefined list of options (for checkboxes, radio buttons and dropdown options), render the full option list with
            // the selected options checked/selected. Otherwise, render the plain answer text.
            $possibleResponses = $response['possibleResponses'] ?? [];

            if (!!count($possibleResponses)) {
                $elementType = $response['elementType'];
                $value = $this->formatReviewFormOptions($possibleResponses, $elementType, $response['selectedResponses'] ?? []);
            } else {
                $value = $this->formatReviewFormText($response['answer'] ?? '', $response['elementType']);
            }

            $sections[] = '<div class="mt-4">'
                . '<label><p>' . PKPString::html2text($question) . '</p></label>'
                . $value
                . '</div>';
        }

        return implode($sections);
    }

    /**
     * Format a list of options for a review form question.
     *
     * @param array $possibleResponses The full list of option labels keyed by their option index.
     * @param int $elementType The review form element type.
     * @param array $selectedIndices The indices of the selected options.
     *
     * @throws Exception
     *
     * @return string The HTML for the rendered list of options.
     */
    protected function formatReviewFormOptions(array $possibleResponses, int $elementType, array $selectedIndices): string
    {
        $selectedIndices = array_map('intval', $selectedIndices);
        $items = [];

        if (!in_array($elementType, [
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_RADIO_BUTTONS,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX
        ])) {
            throw new Exception('SubmissionReviewEventLogGridHandler: Invalid element type encountered');
        }

        foreach ($possibleResponses as $index => $label) {
            if ($elementType === ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX) {
                $items[] = '<option disabled' . (in_array((int)$index, $selectedIndices) ? ' selected' : '') . '>' . PKPString::stripUnsafeHtml($label) . '</option>';
            } else {
                $inputType = $elementType === ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_CHECKBOXES ? 'checkbox' : 'radio';
                $checked = in_array((int)$index, $selectedIndices, true) ? ' checked' : '';
                $items[] = '<li>'
                    . '<label>'
                    . '<input type="' . $inputType . '" disabled' . $checked . '> '
                    . PKPString::html2text($label)
                    . '</label>'
                    . '</li>';
            }
        }

        if ($elementType === ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_DROP_DOWN_BOX) {
            return '<select disabled>' . implode('', $items) . '</select>';
        }

        return '<ul class="checkbox_and_radiobutton">' . implode('', $items) . '</ul>';
    }

    /**
     * Format a text field answer for a review form question.
     */
    protected function formatReviewFormText(string $answer, $elementType): string
    {
        if (!in_array($elementType, [
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD,
            ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXT_FIELD
        ])) {
            throw new Exception('SubmissionReviewEventLogGridHandler: Invalid element type encountered');
        }

        if ($elementType === ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_TEXTAREA) {
            return '<textarea readonly="readonly" rows="4" cols="40">' . PKPString::html2text($answer) . '</textarea>';
        }

        return '<div class="' . ($elementType === ReviewFormElement::REVIEW_FORM_ELEMENT_TYPE_SMALL_TEXT_FIELD ? 'pkp_helpers_quarter' : '') . '">'
            . '<input class="field text" disabled type="text" value="' . PKPString::html2text($answer) . '"></div>';
    }
}
