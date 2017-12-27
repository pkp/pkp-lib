<?php

/**
 * @file controllers/grid/users/reviewer/AuthorReviewerGridHandler.inc.php
 *
 * Copyright (c) 2014-2017 Simon Fraser University
 * Copyright (c) 2000-2017 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AuthorReviewerGridHandler
 * @ingroup controllers_grid_users_reviewer
 *
 * @brief Handle reviewer grid requests from author workflow in open reviews
 */

/*
- Kustomoi periaate, jolla sisällöt listautuvat: vain hyväksytyt ja avoimet arvioinnit loadData
*/ 

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');

// import reviewer grid specific classes
import('lib.pkp.controllers.grid.users.reviewer.AuthorReviewerGridCellProvider');
import('lib.pkp.controllers.grid.users.reviewer.ReviewerGridRow');

// Reviewer selection types
define('REVIEWER_SELECT_ADVANCED_SEARCH',		0x00000001);
define('REVIEWER_SELECT_CREATE',			0x00000002);
define('REVIEWER_SELECT_ENROLL_EXISTING',		0x00000003);

class AuthorReviewerGridHandler extends GridHandler {

	/** @var Submission */
	var $_submission;

	/** @var integer */
	var $_stageId;


	/**
	 * Constructor
	 */
	function __construct() {
		parent::__construct();

		$this->addRoleAssignment(
			array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR),
			array('fetchGrid', 'fetchRow', 'readReview', 'reviewRead')
		);

	}
	
	//
	// Getters and Setters
	//
	/**
	 * Get the authorized submission.
	 * @return Submission
	 */
	function getSubmission() {
		return $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
	}

	/**
	 * Get the review stage id.
	 * @return integer
	 */
	function getStageId() {
		return $this->_stageId;
	}

	/**
	 * Get review round object.
	 * @return ReviewRound
	 */
	function getReviewRound() {
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		if (is_a($reviewRound, 'ReviewRound')) {
			return $reviewRound;
		} else {
			$reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);
			$reviewRoundId = $reviewAssignment->getReviewRoundId();
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$reviewRound = $reviewRoundDao->getById($reviewRoundId);
			return $reviewRound;
		}
	}	

	//
	// Overridden methods from PKPHandler
	//
	function initialize($request, $args = null) {
		parent::initialize($request, $args);

		// Load submission-specific translations
		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_EDITOR,
			LOCALE_COMPONENT_PKP_REVIEWER,
			LOCALE_COMPONENT_APP_EDITOR
		);

		$this->setTitle('user.role.reviewers');

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
				array('anyhtml' => true)
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
	function authorize($request, &$args, $roleAssignments) {
		$stageId = $request->getUserVar('stageId'); // This is being validated in WorkflowStageAccessPolicy

		// Not all actions need a stageId. Some work off the reviewAssignment which has the type and round.
		$this->_stageId = (int)$stageId;

		// Get the stage access policy
		import('lib.pkp.classes.security.authorization.WorkflowStageAccessPolicy');
		$workflowStageAccessPolicy = new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId);

		// Add policy to ensure there is a review round id.
		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
		$workflowStageAccessPolicy->addPolicy(new ReviewRoundRequiredPolicy($request, $args, 'reviewRoundId', array('fetchGrid', 'fetchRow')));

		// Add policy to ensure there is a review assignment for certain operations.
		import('lib.pkp.classes.security.authorization.internal.ReviewAssignmentRequiredPolicy');
		$workflowStageAccessPolicy->addPolicy(new ReviewAssignmentRequiredPolicy($request, $args, 'reviewAssignmentId', array('readReview', 'reviewRead') ));
		$this->addPolicy($workflowStageAccessPolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}	

	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::loadData()
	 */
	protected function loadData($request, $filter) {
		// Get the existing review assignments for this submission
		// Only show open requests that have been accepted
		$reviewRound = $this->getReviewRound();
		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		return $reviewAssignmentDao->getOpenReviewsByReviewRoundId($reviewRound->getId());
	}

	/**
	 * Open a modal to read the reviewer's review and
	 * download any files they may have uploaded
	 * @param $args array
	 * @param $request PKPRequest
	 * @return JSONMessage JSON object
	 */
	function readReview($args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$reviewAssignment = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ASSIGNMENT);

		$templateMgr->assign(array(
			'submission' => $this->getSubmission(),
			'reviewAssignment' => $reviewAssignment,
			'reviewerRecommendationOptions' =>ReviewAssignment::getReviewerRecommendationOptions(),
		));

		if ($reviewAssignment->getReviewFormId()) {
			// Retrieve review form
			$context = $request->getContext();
			$reviewFormElementDao = DAORegistry::getDAO('ReviewFormElementDAO');
			$reviewFormElements = $reviewFormElementDao->getByReviewFormId($reviewAssignment->getReviewFormId());
			$reviewFormResponseDao = DAORegistry::getDAO('ReviewFormResponseDAO');
			$reviewFormResponses = $reviewFormResponseDao->getReviewReviewFormResponseValues($reviewAssignment->getId());
			$reviewFormDao = DAORegistry::getDAO('ReviewFormDAO');
			$reviewformid = $reviewAssignment->getReviewFormId();
			$reviewForm = $reviewFormDao->getById($reviewAssignment->getReviewFormId(), Application::getContextAssocType(), $context->getId());
			$templateMgr->assign(array(
				'reviewForm' => $reviewForm,
				'reviewFormElements' => $reviewFormElements,
				'reviewFormResponses' => $reviewFormResponses,
				'disabled' => true,
			));
		} else {
			// Retrieve reviewer comments. Skip private comments.
			$submissionCommentDao = DAORegistry::getDAO('SubmissionCommentDAO');
			$templateMgr->assign(array(
				'comments' => $submissionCommentDao->getReviewerCommentsByReviewerId($reviewAssignment->getSubmissionId(), null, $reviewAssignment->getId(), true),
			));
		}

		// Render the response.
		return $templateMgr->fetchJson('controllers/grid/users/reviewer/authorReadReview.tpl');
	}	

}

?>
