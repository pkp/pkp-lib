<?php

/**
 * @file controllers/grid/users/reviewer/AuthorReviewerGridHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerGridHandler
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Handle reviewer grid requests from author workflow in open reviews
 */

namespace PKP\controllers\grid\users\reviewer;

use APP\core\Application;
use APP\template\TemplateManager;
use PKP\controllers\grid\GridColumn;
use PKP\controllers\grid\GridHandler;
use PKP\db\DAORegistry;
use PKP\security\authorization\internal\ReviewAssignmentRequiredPolicy;
use PKP\security\authorization\internal\ReviewRoundRequiredPolicy;
use PKP\security\authorization\WorkflowStageAccessPolicy;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;

class AuthorReviewerGridHandler extends PKPReviewerGridHandler
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_AUTHOR],
            ['fetchGrid', 'fetchRow', 'readReview', 'reviewRead']
        );
    }

    //
    // Overridden methods from PKPHandler
    //
    /**
     * @see GridHandler::getRowInstance()
     *
     * @return ReviewerGridRow
     */
    protected function getRowInstance()
    {
        return new AuthorReviewerGridRow();
    }

    /**
     * @copydoc GridHandler::initialize()
     *
     * @param null|mixed $args
     */
    public function initialize($request, $args = null)
    {
        parent::initialize($request, $args);

        // Reset actions
        unset($this->_actions[GridHandler::GRID_ACTION_POSITION_ABOVE]);

        // Columns
        $cellProvider = new AuthorReviewerGridCellProvider();
        $this->addColumn(
            new GridColumn(
                'name',
                'user.name',
                null,
                null,
                $cellProvider
            )
        );

        // Add a column for the status of the review.
        $this->addColumn(
            new GridColumn(
                'considered',
                'common.status',
                null,
                null,
                $cellProvider,
                ['anyhtml' => true]
            )
        );

        // Add a column for the review method
        $this->addColumn(
            new GridColumn(
                'method',
                'common.type',
                null,
                null,
                $cellProvider
            )
        );

        // Add a column for the status of the review.
        $this->addColumn(
            new GridColumn(
                'actions',
                'grid.columns.actions',
                null,
                null,
                $cellProvider
            )
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {

        // Bypass the parent authorization checks
        $this->isAuthorGrid = true;

        $stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy

        // Not all actions need a stageId. Some work off the reviewAssignment which has the type and round.
        $this->_stageId = (int)$stageId;

        // Get the stage access policy
        $workflowStageAccessPolicy = new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId);

        // Add policy to ensure there is a review round id.
        $workflowStageAccessPolicy->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', ['fetchGrid', 'fetchRow']));

        // Add policy to ensure there is a review assignment for certain operations.
        $workflowStageAccessPolicy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId', ['readReview', 'reviewRead'], [SUBMISSION_REVIEW_METHOD_OPEN]));
        $this->addPolicy($workflowStageAccessPolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    //
    // Overridden methods from GridHandler
    //
    /**
     * @see GridHandler::loadData()
     */
    protected function loadData($request, $filter)
    {
        // Get the existing review assignments for this submission
        // Only show open requests that have been accepted
        $reviewRound = $this->getReviewRound();
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        return $reviewAssignmentDao->getOpenReviewsByReviewRoundId($reviewRound->getId());
    }

    /**
     * Open a modal to read the reviewer's review and
     * download any files they may have uploaded
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function readReview($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $reviewAssignment = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT);

        $templateMgr->assign([
            'submission' => $this->getSubmission(),
            'reviewAssignment' => $reviewAssignment,
            'reviewerRecommendationOptions' => ReviewAssignment::getReviewerRecommendationOptions(),
        ]);

        if ($reviewAssignment->getReviewFormId()) {
            // Retrieve review form
            $context = $request->getContext();
            $reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO'); /** @var ReviewFormElementDAO $reviewFormElementDao */
            // Get review form elements visible for authors
            $reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId(), null, true);
            $reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO'); /** @var ReviewFormResponseDAO $reviewFormResponseDao */
            $reviewFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
            $reviewFormDao = DAORegistry::getDAO('ReviewFormDAO'); /** @var ReviewFormDAO $reviewFormDao */
            $reviewformid = $reviewAssignment->getReviewFormId();
            $reviewForm = $reviewFormDao->getById($reviewAssignment->getReviewFormId(), Application::getContextAssocType(), $context->getId());
            $templateMgr->assign([
                'reviewForm' => $reviewForm,
                'reviewFormElements' => $reviewFormElements,
                'reviewFormResponses' => $reviewFormResponses,
                'disabled' => true,
            ]);
        } else {
            // Retrieve reviewer comments. Skip private comments.
            $submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO'); /** @var SubmissionCommentDAO $submissionCommentDao */
            $templateMgr->assign([
                'comments' => $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), null, $reviewAssignment->getId(), true),
            ]);
        }

        // Render the response.
        return $templateMgr->fetchJson('controllers/grid/users/reviewer/authorReadReview.tpl');
    }
}
