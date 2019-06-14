<?php
/**
 * @file components/listPanels/PKPSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for displaying submissions in the dashboard
 */

namespace PKP\components\listPanels;
use PKP\components\listPanels\ListPanel;

import('lib.pkp.classes.submission.PKPSubmission');
import('classes.core.Services');

abstract class PKPSubmissionsListPanel extends ListPanel {

	/** @copydoc ListPanel::$count */
	public $count = 20;

	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {
		$request = \Application::get()->getRequest();

		$config = parent::getConfig();

		// URL to add a new submission
		$config['addUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_PAGE,
			null,
			'submission',
			'wizard'
		);

		// URL to view info center for a submission
		$config['infoUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'informationCenter.SubmissionInformationCenterHandler',
			'viewInformationCenter',
			null,
			array('submissionId' => '__id__')
		);

		// URL to assign a participant
		$config['assignParticipantUrl'] = $request->getDispatcher()->url(
			$request,
			ROUTE_COMPONENT,
			null,
			'grid.users.stageParticipant.StageParticipantGridHandler',
			'addParticipant',
			null,
			array('submissionId' => '__id__', 'stageId' => '__stageId__')
		);

		$config['filters'] = [
			array(
				'filters' => array(
					array(
						'param' => 'isOverdue',
						'value' => true,
						'title' => __('common.overdue'),
					),
					array(
						'param' => 'isIncomplete',
						'value' => true,
						'title' => __('submissions.incomplete'),
					),
				),
			),
			array(
				'heading' => __('settings.roles.stages'),
				'filters' => $this->getWorkflowStages(),
			),
		];

		// Load grid localisation files
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
		\AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		$config['i18n'] = array_merge($config['i18n'], [
			'id' => __('common.id'),
			'add' => __('submission.submit.newSubmissionSingle'),
			'empty' => __('submission.list.empty'),
			'loadMore' => __('grid.action.moreItems'),
			'incomplete' => __('submissions.incomplete'),
			'delete' => __('common.delete'),
			'infoCenter' => __('submission.list.infoCenter'),
			'yes' => __('common.yes'),
			'no' => __('common.no'),
			'deleting' => __('common.deleting'),
			'currentStage' => __('submission.list.currentStage'),
			'confirmDelete' => __('submission.list.confirmDelete'),
			'responseDue' => __('submission.list.responseDue'),
			'reviewDue' => __('submission.list.reviewDue'),
			'reviewComplete' => __('submission.list.reviewComplete'),
			'reviewCancelled' => __('submission.list.reviewCancelled'),
			'viewSubmission' => __('submission.list.viewSubmission'),
			'reviewsCompleted' => __('submission.list.reviewsCompleted'),
			'revisionsSubmitted' => __('submission.list.revisionsSubmitted'),
			'copyeditsSubmitted' => __('submission.list.copyeditsSubmitted'),
			'galleysCreated' => __('submission.list.galleysCreated'),
			'filesPrepared' => __('submission.list.filesPrepared'),
			'discussions' => __('submission.list.discussions'),
			'assignEditor' => __('submission.list.assignEditor'),
			'dualWorkflowLinks' => __('submission.list.dualWorkflowLinks'),
			'reviewerWorkflowLink' => __('submission.list.reviewerWorkflowLink'),
			'incompleteSubmissionNotice' => __('submission.list.incompleteSubmissionNotice'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
			'paginationLabel' => __('common.pagination.label'),
			'goToLabel' => __('common.pagination.goToPage'),
			'pageLabel' => __('common.pageNumber'),
			'nextPageLabel' => __('common.pagination.next'),
			'previousPageLabel' => __('common.pagination.previous'),
		]);

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		// Provide required constants
		import('lib.pkp.classes.submission.reviewRound.ReviewRound');
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
		import('lib.pkp.classes.services.PKPSubmissionService'); // STAGE_STATUS_SUBMISSION_UNASSIGNED
		$templateMgr = \TemplateManager::getManager($request);
		$templateMgr->setConstants([
			'WORKFLOW_STAGE_ID_SUBMISSION',
			'WORKFLOW_STAGE_ID_INTERNAL_REVIEW',
			'WORKFLOW_STAGE_ID_EXTERNAL_REVIEW',
			'WORKFLOW_STAGE_ID_EDITING',
			'WORKFLOW_STAGE_ID_PRODUCTION',
			'STAGE_STATUS_SUBMISSION_UNASSIGNED',
			'REVIEW_ROUND_STATUS_PENDING_REVIEWERS',
			'REVIEW_ROUND_STATUS_REVIEWS_READY',
			'REVIEW_ROUND_STATUS_REVIEWS_COMPLETED',
			'REVIEW_ROUND_STATUS_REVIEWS_OVERDUE',
			'REVIEW_ROUND_STATUS_REVISIONS_REQUESTED',
			'REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED',
			'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW',
			'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED',
			'REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE',
			'REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE',
			'REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE',
			'REVIEW_ASSIGNMENT_STATUS_ACCEPTED',
			'REVIEW_ASSIGNMENT_STATUS_RECEIVED',
			'REVIEW_ASSIGNMENT_STATUS_COMPLETE',
			'REVIEW_ASSIGNMENT_STATUS_THANKED',
			'REVIEW_ASSIGNMENT_STATUS_CANCELLED',
			'REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY',
			'REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED',
		]);

		return $config;
	}

	/**
	 * Helper method to get the items property according to the self::$getParams
	 *
	 * @param Request $request
	 * @return array
	 */
	public function getItems($request) {
		$submissionService = \Services::get('submission');
		$submissions = $submissionService->getMany($this->_getItemsParams());
		$items = [];
		if (!empty($submissions)) {
			foreach ($submissions as $submission) {
				$items[] = $submissionService->getBackendListProperties($submission, ['request' => $request]);
			}
		}

		return $items;
	}

	/**
	 * Helper method to get the itemsMax property according to self::$getParams
	 *
	 * @return int
	 */
	public function getItemsMax() {
		return \Services::get('submission')->getMax($this->_getItemsParams());
	}

	/**
	 * Helper method to compile initial params to get items
	 *
	 * @return array
	 */
	protected function _getItemsParams() {
		$request = \Application::get()->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		return array_merge(
			array(
				'contextId' => $contextId,
				'count' => $this->count,
				'offset' => 0,
			),
			$this->getParams
		);
	}
}
