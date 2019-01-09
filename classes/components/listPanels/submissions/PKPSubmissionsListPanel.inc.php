<?php
/**
 * @file components/listPanels/submissions/PKPSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsListPanel
 * @ingroup controllers_list
 *
 * @brief Instantiates and manages a UI component to list submissions.
 */
import('lib.pkp.classes.components.listPanels.ListPanel');
import('lib.pkp.classes.db.DBResultRange');
import('lib.pkp.classes.submission.Submission');
import('classes.core.Services');

abstract class PKPSubmissionsListPanel extends ListPanel {

	/** @var int Count of items to retrieve in initial page/request */
	public $_count = 20;

	/** @var array Query parameters to pass with every GET request */
	public $_getParams = array();

	/** @var string Used to generate URLs to API endpoints for this component. */
	public $_apiPath = '_submissions';

	/**
	 * @copydoc ListPanel::init()
	 */
	public function init( $args = array() ) {
		parent::init($args);

		$this->_count = isset($args['count']) ? (int) $args['count'] : $this->_count;
		$this->_getParams = isset($args['getParams']) ? $args['getParams'] : $this->_getParams;
	}

	/**
	 * @copydoc ListPanel::getConfig()
	 */
	public function getConfig() {

		$request = Application::getRequest();

		$config = array();

		if ($this->_lazyLoad) {
			$config['lazyLoad'] = true;
		} else {
			$config['items'] = $this->getItems();
			$config['itemsMax'] = $this->getItemsMax();
		}

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

		$config['apiPath'] = $this->_apiPath;

		$config['count'] = $this->_count;
		$config['page'] = 1;

		$config['getParams'] = $this->_getParams;

		$config['filters'] = array(
			'attention' => array(
				'filters' => array(
					array(
						'param' => 'isOverdue',
						'val' => true,
						'title' => __('common.overdue'),
					),
					array(
						'param' => 'isIncomplete',
						'val' => true,
						'title' => __('submissions.incomplete'),
					),
				),
			),
			'stageIds' => array(
				'heading' => __('settings.roles.stages'),
				'filters' => $this->getWorkflowStages(),
			),
		);

		// Load grid localisation files
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_GRID);
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);

		$config['i18n'] = array(
			'id' => __('common.id'),
			'title' => __($this->_title),
			'add' => __('submission.submit.newSubmissionSingle'),
			'search' => __('common.search'),
			'clearSearch' => __('common.clearSearch'),
			'itemCount' => __('submission.list.count'),
			'itemsOfTotal' => __('submission.list.itemsOfTotal'),
			'loadMore' => __('grid.action.moreItems'),
			'loading' => __('common.loading'),
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
			'filter' => __('common.filter'),
			'filterRemove' => __('common.filterRemove'),
			'orderUp' => __('common.orderUp'),
			'orderDown' => __('common.orderDown'),
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
			'selectAllLabel' => __('common.selectAll'),
			'viewMore' => __('list.viewMore'),
			'viewLess' => __('list.viewLess'),
		);

		// Attach a CSRF token for post requests
		$config['csrfToken'] = $request->getSession()->getCSRFToken();

		// Provide required constants
		import('lib.pkp.classes.submission.reviewRound.ReviewRound');
		import('lib.pkp.classes.submission.reviewAssignment.ReviewAssignment');
		import('lib.pkp.classes.services.PKPSubmissionService'); // STAGE_STATUS_SUBMISSION_UNASSIGNED
		$config['_constants'] = array(
			'WORKFLOW_STAGE_ID_SUBMISSION' => WORKFLOW_STAGE_ID_SUBMISSION,
			'WORKFLOW_STAGE_ID_INTERNAL_REVIEW' => WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
			'WORKFLOW_STAGE_ID_EXTERNAL_REVIEW' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
			'WORKFLOW_STAGE_ID_EDITING' => WORKFLOW_STAGE_ID_EDITING,
			'WORKFLOW_STAGE_ID_PRODUCTION' => WORKFLOW_STAGE_ID_PRODUCTION,
			'STAGE_STATUS_SUBMISSION_UNASSIGNED' => STAGE_STATUS_SUBMISSION_UNASSIGNED,
			'REVIEW_ROUND_STATUS_PENDING_REVIEWERS' => REVIEW_ROUND_STATUS_PENDING_REVIEWERS,
			'REVIEW_ROUND_STATUS_REVIEWS_READY' => REVIEW_ROUND_STATUS_REVIEWS_READY,
			'REVIEW_ROUND_STATUS_REVIEWS_COMPLETED' => REVIEW_ROUND_STATUS_REVIEWS_COMPLETED,
			'REVIEW_ROUND_STATUS_REVIEWS_OVERDUE' => REVIEW_ROUND_STATUS_REVIEWS_OVERDUE,
			'REVIEW_ROUND_STATUS_REVISIONS_REQUESTED' => REVIEW_ROUND_STATUS_REVISIONS_REQUESTED,
			'REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED' => REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED,
			'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW' => REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW,
			'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED' => REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED,
			'REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE' => REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE,
			'REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE' => REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE,
			'REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE' => REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE,
			'REVIEW_ASSIGNMENT_STATUS_ACCEPTED' => REVIEW_ASSIGNMENT_STATUS_ACCEPTED,
			'REVIEW_ASSIGNMENT_STATUS_RECEIVED' => REVIEW_ASSIGNMENT_STATUS_RECEIVED,
			'REVIEW_ASSIGNMENT_STATUS_COMPLETE' => REVIEW_ASSIGNMENT_STATUS_COMPLETE,
			'REVIEW_ASSIGNMENT_STATUS_THANKED' => REVIEW_ASSIGNMENT_STATUS_THANKED,
			'REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY' => REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY,
			'REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED' => REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED,
		);

		return $config;
	}

	/**
	 * @copydoc ListPanel::getItems()
	 */
	public function getItems() {
		$submissionService = Services::get('submission');
		$submissions = $submissionService->getMany($this->_getItemsParams());
		$items = array();
		if (!empty($submissions)) {
			$propertyArgs = array(
				'request' => Application::getRequest(),
			);
			foreach ($submissions as $submission) {
				$items[] = $submissionService->getBackendListProperties($submission, $propertyArgs);
			}
		}

		return $items;
	}

	/**
	 * @copydoc ListPanel::getItemsMax()
	 */
	public function getItemsMax() {
		return Services::get('submission')->getMax($this->_getItemsParams());
	}

	/**
	 * @copydoc ListPanel::_getItemsParams()
	 */
	protected function _getItemsParams() {
		$request = Application::getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;

		return array_merge(
			array(
				'contextId' => $contextId,
				'count' => $this->_count,
				'offset' => 0,
			),
			$this->_getParams
		);
	}
}
