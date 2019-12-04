<?php

/**
 * @file pages/stats/PKPStatsHandler.inc.php
 *
 * Copyright (c) 2013-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsHandler
 * @ingroup pages_stats
 *
 * @brief Handle requests for statistics pages.
 */

import('classes.handler.Handler');
import('classes.statistics.StatisticsHelper');

class PKPStatsHandler extends Handler {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
			array('editorial', 'publications', 'users')
		);
	}

	/**
	 * @see PKPHandler::authorize()
	 * @param $request PKPRequest
	 * @param $args array
	 * @param $roleAssignments array
	 */
	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}


	//
	// Public handler methods.
	//
	/**
	 * Display editorial stats about the submission workflow process
	 *
	 * @param array $args
	 * @param Request $request
	 */
	public function editorial($args, $request) {
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();

		if (!$context) {
			$dispatcher->handle404();
		}

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$dateStart = date('Y-m-d', strtotime('-91 days'));
		$dateEnd = date('Y-m-d', strtotime('yesterday'));

		$args = [
			'contextIds' => [$context->getId()],
		];

		$totals = Services::get('editorialStats')->getOverview($args);
		$dateRangeTotals = Services::get('editorialStats')->getOverview(
			array_merge(
				$args,
				[
					'dateStart' => $dateStart,
					'dateEnd' => $dateEnd,
				]
			)
		);

		// Stats that should be converted to percentages
		$percentageStats = [
			'acceptanceRate',
			'declineRate',
			'declinedDeskRate',
			'declinedReviewRate',
			'declinedOtherRate',
		];

		// Compile table rows
		$tableRows = [];
		foreach ($totals as $key => $total) {
			$row = [
				'key' => $key,
				'name' => $this->_getStatName($key),
			];
			if (in_array($key, $percentageStats)) {
				$row['total'] = ($total * 100) . '%';
				$row['dateRange'] = ($dateRangeTotals[$key] * 100) . '%';
			} else {
				$row['total'] = $total;
				$row['dateRange'] = $dateRangeTotals[$key];
			}
			$description = $this->_getStatDescription($key);
			if ($description) {
				$row['description'] = $description;
			}
			$tableRows[] = $row;
		}

		// Get the worflow stage counts
		// $stageIds = Application::get()->getApplicationStages();
		$activeByStage = [];
		foreach (Application::get()->getApplicationStages() as $stageId) {
			$activeByStage[] = [
				'name' => Application::get()->getWorkflowStageName($stageId),
				'count' => Services::get('editorialStats')->countActiveByStages($stageId),
				'color' => Application::get()->getWorkflowStageColor($stageId),
			];
		}

		$statsComponent = new \PKP\components\PKPStatsEditorialContainer(
			$dispatcher->url($request, ROUTE_API, $context->getPath(), 'stats/editorial'),
			[
				'activeByStage' => $activeByStage,
				'dateStart' => $dateStart,
				'dateEnd' => $dateEnd,
				'dateRangeOptions' => [
					[
						'dateStart' => date('Y-m-d', strtotime('-91 days')),
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last90Days'),
					],
					[
						'dateStart' => date('Y-m-d', strtotime(date('Y') . '-01-01')),
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.thisYear'),
					],
					[
						'dateStart' => date('Y-m-d', strtotime((date('Y') - 1) . '-01-01')),
						'dateEnd' => date('Y-m-d', strtotime((date('Y') - 1) . '-12-31')),
						'label' => __('stats.dateRange.lastYear'),
					],
					[
						'dateStart' => date('Y-m-d', strtotime((date('Y') - 2) . '-01-01')),
						'dateEnd' => date('Y-m-d', strtotime((date('Y') - 1) . '-12-31')),
						'label' => __('stats.dateRange.lastTwoYears'),
					],
				],
				'percentageStats' => $percentageStats,
				'tableColumns' => [
					[
						'name' => 'name',
						'label' => __('common.name'),
						'value' => 'name',
					],
					[
						'name' => 'dateRange',
						'label' => $dateStart . ' — ' . $dateEnd,
						'value' => 'dateRange',
					],
					[
						'name' => 'total',
						'label' => __('stats.total'),
						'value' => 'total',
					],
				],
				'tableRows' => $tableRows,
			]
		);

		$templateMgr->assign('statsComponent', $statsComponent);

		$templateMgr->display('stats/editorial.tpl');
	}

	/**
	 * Display published submissions statistics page
   *
	 * @param $request PKPRequest
	 * @param $args array
	 */
	public function publications($args, $request) {
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();

		if (!$context) {
			$dispatcher->handle404();
		}

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION);
		AppLocale::requireComponents(LOCALE_COMPONENT_APP_SUBMISSION);

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$dateStart = date('Y-m-d', strtotime('-31 days'));
		$dateEnd = date('Y-m-d', strtotime('yesterday'));
		$count = 30;

		$timeline = Services::get('stats')->getTimeline(STATISTICS_DIMENSION_DAY, [
      'assocTypes' => ASSOC_TYPE_SUBMISSION,
      'contextIds' => $context->getID(),
			'count' => $count,
			'dateStart' => $dateStart,
      'dateEnd' => $dateEnd,
		]);

		$statsComponent = new \PKP\components\PKPStatsPublicationContainer(
			$dispatcher->url($request, ROUTE_API, $context->getPath(), 'stats/publications'),
			[
				'timeline' => $timeline,
				'timelineInterval' => STATISTICS_DIMENSION_DAY,
				'timelineType' => 'abstract',
				'tableColumns' => [
					[
						'name' => 'title',
						'label' => __('common.title'),
					],
					[
						'name' => 'abstractViews',
						'label' => __('submission.abstractViews'),
						'value' => 'abstractViews',
					],
					[
						'name' => 'galleyViews',
						'label' => __('stats.fileViews'),
						'value' => 'galleyViews',
					],
					[
						'name' => 'pdf',
						'label' => __('stats.pdf'),
						'value' => 'pdfViews',
					],
					[
						'name' => 'html',
						'label' => __('stats.html'),
						'value' => 'htmlViews',
					],
					[
						'name' => 'other',
						'label' => __('common.other'),
						'value' => 'otherViews',
					],
					[
						'name' => 'total',
						'label' => __('stats.total'),
						'value' => 'total',
						'orderBy' => 'total',
						'initialOrderDirection' => true,
					],
				],
				'count' => $count,
				'dateStart' => $dateStart,
				'dateEnd' => $dateEnd,
				'dateRangeOptions' => [
					[
						'dateStart' => $dateStart,
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last30Days'),
					],
					[
						'dateStart' => date('Y-m-d', strtotime('-91 days')),
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last90Days'),
					],
					[
						'dateStart' => date('Y-m-d', strtotime('-12 months')),
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last12Months'),
					],
					[
						'dateStart' => '',
						'dateEnd' => '',
						'label' => __('stats.dateRange.allDates'),
					],
				],
				'orderBy' => 'total',
				'orderDirection' => true,
			]
		);

		$templateMgr->assign('statsComponent', $statsComponent);

		$templateMgr->display('stats/publications.tpl');
	}

	/**
	 * Display users stats
	 *
	 * @param array $args
	 * @param Request $request
	 */
	public function users($args, $request) {
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();

		if (!$context) {
			$dispatcher->handle404();
		}

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$templateMgr->assign('statsComponentData', [
			'tableColumns' => [
				[
					'name' => 'name',
					'label' => __('common.name'),
					'value' => 'name',
				],
				[
					'name' => 'total',
					'label' => __('stats.total'),
					'value' => 'total',
				],
			],
			'tableRows' => Services::get('user')->getRolesOverview(['contextId' => $context->getId()]),
		]);

		$templateMgr->display('stats/users.tpl');
	}

	/**
	 *
	 */
	protected function _getStatName($stat) {
		switch ($stat) {
			case 'submissionsReceived': return __('stats.name.submissionsReceived');
			case 'submissionsAccepted': return __('stats.name.submissionsAccepted');
			case 'submissionsDeclined': return __('stats.name.submissionsDeclined');
			case 'submissionsDeclinedDeskReject': return ' ' . __('stats.name.submissionsDeclinedDeskReject');
			case 'submissionsDeclinedPostReview': return ' ' . __('stats.name.submissionsDeclinedPostReview');
			case 'submissionsDeclinedOther': return ' ' . __('stats.name.submissionsDeclinedOther');
			case 'submissionsPublished': return __('stats.name.submissionsPublished');
			case 'averageDaysToDecision': return __('stats.name.averageDaysToDecision');
			case 'averageDaysToAccept': return ' ' . __('stats.name.averageDaysToAccept');
			case 'averageDaysToReject': return ' ' . __('stats.name.averageDaysToReject');
			case 'acceptanceRate': return __('stats.name.acceptanceRate');
			case 'declineRate': return __('stats.name.declineRate');
			case 'declinedDeskRate': return ' ' . __('stats.name.declinedDeskRate');
			case 'declinedReviewRate': return ' ' . __('stats.name.declinedReviewRate');
			case 'declinedOtherRate': return ' ' . __('stats.name.declinedOtherRate');
		}
		return $stat;
	}

	protected function _getStatDescription($stat) {
		switch ($stat) {
			case 'averageDaysToDecision': return __('stats.description.averageDaysToDecision');
			case 'acceptanceRate': return __('stats.description.acceptRejectRate');
			case 'declineRate': return __('stats.description.acceptRejectRate');
		}
		return '';
	}
}
