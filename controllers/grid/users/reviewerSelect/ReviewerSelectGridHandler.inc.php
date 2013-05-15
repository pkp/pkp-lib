<?php

/**
 * @file controllers/grid/users/reviewerSelect/ReviewerSelectGridHandler.inc.php
 *
 * Copyright (c) 2000-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ReviewerSelectGridHandler
 * @ingroup controllers_grid_users_reviewerSelect
 *
 * @brief Handle reviewer selector grid requests.
 */

// import grid base classes
import('lib.pkp.classes.controllers.grid.GridHandler');


// import author grid specific classes
import('lib.pkp.controllers.grid.users.reviewerSelect.ReviewerSelectGridCellProvider');
import('lib.pkp.controllers.grid.users.reviewerSelect.ReviewerSelectGridRow');

class ReviewerSelectGridHandler extends GridHandler {
	/**
	 * Constructor
	 */
	function ReviewerSelectGridHandler() {
		parent::GridHandler();

		$this->addRoleAssignment(
			array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT),
			array('fetchGrid')
		);
	}

	//
	// Implement template methods from PKPHandler
	//
	/**
	 * @see PKPHandler::authorize()
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 */
	function authorize($request, &$args, $roleAssignments) {
		$stageId = (int)$request->getUserVar('stageId');

		import('classes.security.authorization.WorkflowStageAccessPolicy');
		$this->addPolicy(new WorkflowStageAccessPolicy($request, $args, $roleAssignments, 'submissionId', $stageId));

		import('lib.pkp.classes.security.authorization.internal.ReviewRoundRequiredPolicy');
		$this->addPolicy(new ReviewRoundRequiredPolicy($request, $args));

		return parent::authorize($request, $args, $roleAssignments);
	}

	/*
	 * Configure the grid
	 * @param $request PKPRequest
	 */
	function initialize($request) {
		parent::initialize($request);

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_APP_EDITOR
		);
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		// Columns
		$cellProvider = new ReviewerSelectGridCellProvider();
		$this->addColumn(
			new GridColumn(
				'select',
				'',
				null,
				'controllers/grid/users/reviewerSelect/reviewerSelectRadioButton.tpl',
				$cellProvider,
				array('width' => 5)
			)
		);
		$this->addColumn(
			new GridColumn(
				'name',
				'author.users.contributor.name',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('alignment' => COLUMN_ALIGNMENT_LEFT,
						'width' => 30
					)
			)
		);
		$this->addColumn(
			new GridColumn(
				'done',
				'common.done',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'avg',
				'editor.review.days',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'last',
				'editor.submissions.lastAssigned',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'active',
				'common.active',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider
			)
		);
		$this->addColumn(
			new GridColumn(
				'interests',
				'user.interests',
				null,
				'controllers/grid/gridCell.tpl',
				$cellProvider,
				array('width' => 20)
			)
		);
	}


	//
	// Overridden methods from GridHandler
	//
	/**
	 * @see GridHandler::getRowInstance()
	 * @return ReviewerSelectGridRow
	 */
	function getRowInstance() {
		return new ReviewerSelectGridRow();
	}

	/**
	 * @see GridHandler::renderFilter()
	 */
	function renderFilter($request) {
		return parent::renderFilter($request, $this->getFilterSelectionData($request));
	}

	/**
	 * @see GridHandler::loadData()
	 */
	function loadData($request, $filter) {
		$interests = $filter['interestSearchKeywords'];
		$reviewerValues = $filter['reviewerValues'];

		// Retrieve the authors associated with this submission to be displayed in the grid
		$doneMin = $reviewerValues['doneMin'];
		$doneMax = $reviewerValues['doneMax'];
		$avgMin = $reviewerValues['avgMin'];
		$avgMax = $reviewerValues['avgMax'];
		$lastMin = $reviewerValues['lastMin'];
		$lastMax = $reviewerValues['lastMax'];
		$activeMin = $reviewerValues['activeMin'];
		$activeMax = $reviewerValues['activeMax'];

		$userDao = DAORegistry::getDAO('UserDAO');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		return $userDao->getFilteredReviewers(
			$submission->getContextId(), $doneMin, $doneMax, $avgMin, $avgMax,
			$lastMin, $lastMax, $activeMin, $activeMax, $interests,
			$submission->getId(), $reviewRound->getId()
		);
	}

	/**
	 * @see GridHandler::getFilterSelectionData()
	 * @return array Filter selection data.
	 */
	function getFilterSelectionData($request) {
		$form = $this->getFilterForm();

		// Only read form data if the clientSubmit flag has been checked
		$clientSubmit = (boolean) $request->getUserVar('clientSubmit');

		$form->readInputData();
		if($clientSubmit && $form->validate()) {
			return $form->getFilterSelectionData();
		} else {
			// Load defaults
			return $this->_getFilterData();
		}
	}

	/**
	 * @see GridHandler::getFilterForm()
	 * @return Form
	 */
	function getFilterForm() {
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = $this->getAuthorizedContextObject(ASSOC_TYPE_WORKFLOW_STAGE);
		$reviewRound = $this->getAuthorizedContextObject(ASSOC_TYPE_REVIEW_ROUND);
		import('lib.pkp.controllers.grid.users.reviewerSelect.form.AdvancedSearchReviewerFilterForm');
		return new AdvancedSearchReviewerFilterForm($submission, $stageId, $reviewRound->getId());
	}

	/**
	 * Get the default filter data for this grid
	 * @return array
	 */
	function _getFilterData() {
		$filterData = array();

		$filterData['interestSearchKeywords'] = null;

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewerValues = $reviewAssignmentDao->getAnonymousReviewerStatistics();
		$filterData['reviewerValues'] = $reviewerValues;

		return $filterData;
	}
}

?>
