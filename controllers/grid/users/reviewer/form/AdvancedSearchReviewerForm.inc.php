<?php

/**
 * @file controllers/grid/users/reviewer/form/AdvancedSearchReviewerForm.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class AdvancedSearchReviewerForm
 * @ingroup controllers_grid_users_reviewer_form
 *
 * @brief Form for an advanced search and for adding a reviewer to a submission.
 */

import('lib.pkp.controllers.grid.users.reviewer.form.ReviewerForm');

class AdvancedSearchReviewerForm extends ReviewerForm {
	/**
	 * Constructor.
	 * @param $submission Submission
	 * @param $reviewRound ReviewRound
	 */
	function __construct($submission, $reviewRound) {
		parent::__construct($submission, $reviewRound);
		$this->setTemplate('controllers/grid/users/reviewer/form/advancedSearchReviewerForm.tpl');

		$this->addCheck(new FormValidator($this, 'reviewerId', 'required', 'editor.review.mustSelect'));
	}

	/**
	 * Assign form data to user-submitted data.
	 * @see Form::readInputData()
	 */
	function readInputData() {
		parent::readInputData();

		$this->readUserVars(array('reviewerId'));
	}

	/**
	 * @copydoc Form::fetch()
	 */
	function fetch($request, $template = null, $display = false) {
		// Get submission context
		$submissionContext = Services::get('context')->get($this->getSubmission()->getContextId());

		// Pass along the request vars
		$actionArgs = $request->getUserVars();
		$reviewRound = $this->getReviewRound();
		$actionArgs['reviewRoundId'] = $reviewRound->getId();
		$actionArgs['selectionType'] = REVIEWER_SELECT_ADVANCED_SEARCH;
		// but change the selectionType for each action
		$advancedSearchAction = new LinkAction(
			'advancedSearch',
			new AjaxAction($request->url(null, null, 'reloadReviewerForm', null, $actionArgs)),
			__('manager.reviewerSearch.change'),
			'user_search'
		);

		$this->setReviewerFormAction($advancedSearchAction);

		$reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO');
		$reviewAssignments = $reviewAssignmentDao->getBySubmissionId($this->getSubmissionId(), $this->getReviewRound()->getId());
		$currentlyAssigned = array();
		if (!empty($reviewAssignments)) {
			foreach ($reviewAssignments as $reviewAssignment) {
				$currentlyAssigned[] = (int) $reviewAssignment->getReviewerId();
			}
		}

		// Get user IDs already assigned to this submission, and admins and
		// managers who may have access to author identities and can not guarantee
		// blind reviews
		$warnOnAssignment = array();
		$stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO');
		$stageAssignmentResults = $stageAssignmentDao->getBySubmissionAndStageId($this->getSubmissionId());
		while ($stageAssignment = $stageAssignmentResults->next()) {
			$warnOnAssignment[] = $stageAssignment->getUserId();
		}
		$roleDao = DAORegistry::getDAO('RoleDAO');
		$managerUsersResults = $roleDao->getUsersByRoleId(ROLE_ID_MANAGER, $submissionContext->getId());
		while ($manager = $managerUsersResults->next()) {
			$warnOnAssignment[] = $manager->getId();
		}
		$adminUsersResults = $roleDao->getUsersByRoleId(ROLE_ID_SITE_ADMIN, $submissionContext->getId());
		while ($admin = $adminUsersResults->next()) {
			$warnOnAssignment[] = $admin->getId();
		}
		$warnOnAssignment = array_map('intval', array_values(array_unique($warnOnAssignment)));

		// Get reviewers list
		$selectReviewerListPanel = new \PKP\components\listPanels\PKPSelectReviewerListPanel(
			'selectReviewer',
			__('editor.submission.findAndSelectReviewer'),
			[
				'apiUrl' => $request->getDispatcher()->url(
					$request,
					ROUTE_API,
					$submissionContext->getPath(),
					'users/reviewers'
				),
				'currentlyAssigned' => $currentlyAssigned,
				'getParams' => [
					'contextId' => $submissionContext->getId(),
					'count' => 15,
					'roleIds' => [ROLE_ID_REVIEWER],
				],
				'items' => $items,
				'itemsMax' => $itemsMax,
				'selectorName' => 'reviewerId',
				'selectorType' => 'radio',
				'warnOnAssignment' => $warnOnAssignment,
			]
		);
		$selectReviewerListPanel->set([
			'items' => $selectReviewerListPanel->getItems($request),
			'itemsMax' => $selectReviewerListPanel->getItemsMax(),
		]);

		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('selectReviewerListData', [
			'components' => [
				'selectReviewer' => $selectReviewerListPanel->getConfig(),
			]
		]);

		// Only add actions to forms where user can operate.
		if (array_intersect($this->getUserRoles(), array(ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR))) {
			$actionArgs['selectionType'] = REVIEWER_SELECT_CREATE;
			// but change the selectionType for each action
			$advancedSearchAction = new LinkAction(
				'selectCreate',
				new AjaxAction($request->url(null, null, 'reloadReviewerForm', null, $actionArgs)),
				__('editor.review.createReviewer'),
				'add_user'
			);

			$this->setReviewerFormAction($advancedSearchAction);
			$actionArgs['selectionType'] = REVIEWER_SELECT_ENROLL_EXISTING;
			// but change the selectionType for each action
			$advancedSearchAction = new LinkAction(
				'enrolExisting',
				new AjaxAction($request->url(null, null, 'reloadReviewerForm', null, $actionArgs)),
				__('editor.review.enrollReviewer.short'),
				'enroll_user'
			);

			$this->setReviewerFormAction($advancedSearchAction);
		}

		return parent::fetch($request, $template, $display);
	}
}


