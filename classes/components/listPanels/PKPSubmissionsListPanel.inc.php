<?php
/**
 * @file components/listPanels/PKPSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for displaying submissions in the dashboard
 */

namespace PKP\components\listPanels;

use PKP\components\listPanels\ListPanel;
use PKP\components\forms\FieldSelectUsers;

import('lib.pkp.classes.submission.PKPSubmission');
import('classes.core.Services');

abstract class PKPSubmissionsListPanel extends ListPanel {

	/** @var string URL to the API endpoint where items can be retrieved */
	public $apiUrl = '';

	/** @var integer Number of items to show at one time */
	public $count = 30;

	/** @var array Query parameters to pass if this list executes GET requests  */
	public $getParams = [];

	/** @var boolean Should items be loaded after the component is mounted?  */
	public $lazyLoad = false;

	/** @var integer Count of total items available for list */
	public $itemsMax = 0;

	/** @var boolean Whether to show assigned to editors filter */
	public $includeAssignedEditorsFilter = false;

	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {
		\AppLocale::requireComponents([LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_EDITOR, LOCALE_COMPONENT_APP_EDITOR]);
		$request = \Application::get()->getRequest();
		$context = $request->getContext();

		$config = parent::getConfig();

		$config['apiUrl'] = $this->apiUrl;
		$config['count'] = $this->count;
		$config['getParams'] = $this->getParams;
		$config['lazyLoad'] = $this->lazyLoad;
		$config['itemsMax'] = $this->itemsMax;

		// URL to add a new submission
		if ($context->getData('disableSubmissions')) {
			$config['allowSubmissions'] = false;
		}

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
			[
				'heading' => __('settings.roles.stages'),
				'filters' => $this->getWorkflowStages(),
			],
			[
				'heading' => __('submission.list.activity'),
				'filters' => [
					[
						'title' => __('submission.list.daysSinceLastActivity'),
						'param' => 'daysInactive',
						'value' => 30,
						'min' => 1,
						'max' => 180,
						'filterType' => 'pkp-filter-slider',
					]
				]
			]
		];

		if ($this->includeAssignedEditorsFilter) {
			$assignedEditorsField = new FieldSelectUsers('assignedTo', [
				'label' => __('editor.submissions.assignedTo'),
				'value' => [],
				'apiUrl' => $request->getDispatcher()->url(
					$request,
					ROUTE_API,
					$context->getPath(),
					'users',
					null,
					null,
					['roleIds' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR]]
				),
			]);
			$config['filters'][] = [
				'filters' => [
					[
						'title' => __('editor.submissions.assignedTo'),
						'param' => 'assignedTo',
						'value' => [],
						'filterType' => 'pkp-filter-autosuggest',
						'component' => $assignedEditorsField->component,
						'autosuggestProps' => $assignedEditorsField->getConfig(),
					]
				]
			];
		}

		// Provide required constants
		import('lib.pkp.classes.submission.reviewRound.ReviewRound');
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
		import('lib.pkp.classes.services.PKPSubmissionService'); // STAGE_STATUS_SUBMISSION_UNASSIGNED
		$templateMgr = \TemplateManager::getManager($request);
		$templateMgr->setConstants([
			'STATUS_QUEUED',
			'STATUS_PUBLISHED',
			'STATUS_DECLINED',
			'STATUS_SCHEDULED',
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

		$templateMgr->setLocaleKeys([
			'common.lastActivity',
			'editor.submissionArchive.confirmDelete',
			'submission.list.empty',
			'submission.submit.newSubmissionSingle',
			'submission.review',
			'submissions.incomplete',
			'submission.list.assignEditor',
			'submission.list.copyeditsSubmitted',
			'submission.list.currentStage',
			'submission.list.discussions',
			'submission.list.dualWorkflowLinks',
			'submission.list.galleysCreated',
			'submission.list.infoCenter',
			'submission.list.reviewAssignment',
			'submission.list.responseDue',
			'submission.list.reviewCancelled',
			'submission.list.reviewComplete',
			'submission.list.reviewDue',
			'submission.list.reviewerWorkflowLink',
			'submission.list.reviewsCompleted',
			'submission.list.revisionsSubmitted',
			'submission.list.viewSubmission',
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
		$submissionsIterator = \Services::get('submission')->getMany($this->_getItemsParams());
		$items = [];
		foreach ($submissionsIterator as $submission) {
			$items[] = \Services::get('submission')->getBackendListProperties($submission, ['request' => $request]);
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
