<?php

/**
 * @file controllers/grid/users/reviewer/AuthorReviewerGridCellProvider.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerGridCellProvider
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Base class for a cell provider that can retrieve labels for reviewer grid rows in author workflow
 */

namespace PKP\controllers\grid\users\reviewer;

use APP\facades\Repo;
use PKP\controllers\grid\DataObjectGridCellProvider;
use PKP\controllers\grid\GridHandler;
use PKP\controllers\review\linkAction\ReviewNotesLinkAction;
use PKP\submission\reviewAssignment\ReviewAssignment;

class AuthorReviewerGridCellProvider extends DataObjectGridCellProvider
{
    //
    // Template methods from GridCellProvider
    //
    /**
     * Gathers the state of a given cell given a $row/$column combination
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return string
     */
    public function getCellState($row, $column)
    {
        /** @var ReviewAssignment */
        $reviewAssignment = $row->getData();
        $columnId = $column->getId();
        assert($reviewAssignment instanceof \PKP\core\DataObject && !empty($columnId));
        switch ($columnId) {
            case 'name':
            case 'method':
                return '';
            case 'considered':
            case 'actions':
                return $reviewAssignment->getStatus();
        }
    }

    /**
     * Extracts variables for a given column from a data element
     * so that they may be assigned to template before rendering.
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array
     */
    public function getTemplateVarsFromRowColumn($row, $column)
    {
        /** @var ReviewAssignment */
        $element = $row->getData();
        $columnId = $column->getId();
        assert($element instanceof \PKP\core\DataObject && !empty($columnId));
        switch ($columnId) {
            case 'name':
                return ['label' => $element->getReviewerFullName()];

            case 'method':
                return ['label' => __($element->getReviewMethodKey())];

            case 'considered':
                return ['label' => $this->_getStatusText($this->getCellState($row, $column), $row)];

            case 'actions':
                // Only attach actions to this column. See self::getCellActions()
                return ['label' => ''];
        }

        return parent::getTemplateVarsFromRowColumn($row, $column);
    }

    /**
     * Get cell actions associated with this row/column combination
     *
     * @param \PKP\controllers\grid\GridRow $row
     * @param GridColumn $column
     *
     * @return array an array of LinkAction instances
     */
    public function getCellActions($request, $row, $column, $position = GridHandler::GRID_ACTION_POSITION_DEFAULT)
    {
        $reviewAssignment = $row->getData();
        $actionArgs = [
            'submissionId' => $reviewAssignment->getSubmissionId(),
            'reviewAssignmentId' => $reviewAssignment->getId(),
            'stageId' => $reviewAssignment->getStageId(),
        ];

        $submission = Repo::submission()->get($reviewAssignment->getSubmissionId());

        // Only attach actions to the actions column. The actions and status
        // columns share state values.
        $columnId = $column->getId();
        if ($columnId == 'actions') {
            switch ($this->getCellState($row, $column)) {
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_THANKED:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                    $user = $request->getUser();
                    return [new ReviewNotesLinkAction($request, $reviewAssignment, $submission, $user, 'grid.users.reviewer.AuthorReviewerGridHandler', true)];
                default:
                    return null;
            }
        }
        return parent::getCellActions($request, $row, $column, $position);
    }

    /**
     * Provide meaningful locale keys for the various grid status states.
     *
     * @param string $state
     * @param \PKP\controllers\grid\GridRow $row
     *
     * @return string
     */
    public function _getStatusText($state, $row)
    {
        $reviewAssignment = $row->getData();
        switch ($state) {
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
                return '<span class="state">' . __('editor.review.requestSent') . '</span><span class="details">' . __('editor.review.responseDue', ['date' => substr($reviewAssignment->getDateResponseDue(), 0, 10)]) . '</span>';
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
                return '<span class="state">' . __('editor.review.requestAccepted') . '</span><span class="details">' . __('editor.review.reviewDue', ['date' => substr($reviewAssignment->getDateDue(), 0, 10)]) . '</span>';
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE:
                return $this->_getStatusWithRecommendation('common.complete', $reviewAssignment);
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
                return '<span class="state overdue">' . __('common.overdue') . '</span><span class="details">' . __('editor.review.reviewDue', ['date' => substr($reviewAssignment->getDateDue(), 0, 10)]) . '</span>';
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
                return '<span class="state overdue">' . __('common.overdue') . '</span><span class="details">' . __('editor.review.responseDue', ['date' => substr($reviewAssignment->getDateResponseDue(), 0, 10)]) . '</span>';
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_DECLINED:
                return '<span class="state declined">' . __('common.declined') . '</span>';
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_CANCELLED:
                return '<span class="state cancelled">' . __('common.cancelled') . '</span>';
            case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                return  $this->_getStatusWithRecommendation('editor.review.reviewSubmitted', $reviewAssignment);
            default:
                return '';
        }
    }

    /**
     * Retrieve a formatted HTML string that displays the state of the review
     * with the review recommendation if one exists. Or return just the state.
     * Only works with some states.
     *
     * @param string $statusKey Locale key for status text
     * @param \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment
     *
     * @return string
     */
    public function _getStatusWithRecommendation($statusKey, $reviewAssignment)
    {
        if (!$reviewAssignment->getRecommendation()) {
            return __($statusKey);
        }

        return '<span class="state">' . __($statusKey) . '</span><span class="details">' . __('submission.recommendation', ['recommendation' => $reviewAssignment->getLocalizedRecommendation()]) . '</span>';
    }
}
