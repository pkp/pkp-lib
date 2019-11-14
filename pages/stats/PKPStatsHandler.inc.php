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

use \PKP\Services\EditorialStatisticsService;

class PKPStatsHandler extends Handler {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
			['publishedSubmissions', 'editorialReport']
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
	 * Display published submissions statistics page
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function publishedSubmissions($args, $request) {
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
		$count = 20;

		$params = [
			'count' => $count,
			'dateStart' => $dateStart,
			'dateEnd' => $dateEnd,
			'timeSegment' => 'day',
		];

		$statsService = ServicesContainer::instance()->get('stats');

		// Get total stats
		$totalStatsRecords = $statsService->getTotalSubmissionsStats($context->getId(), $params);
		$totalStats = $statsService->getTotalStatsProperties($totalStatsRecords, [
			'request' => $request,
			'params' => $params
		]);

		// Get submission stats
		$submissionsRecords = $statsService->getOrderedSubmissions($context->getId(), $params);

		$items = [];
		if (!empty($submissionsRecords)) {
			$propertyArgs = [
				'request' => $request,
				'params' => $params
			];
			$slicedSubmissionsRecords = array_slice($submissionsRecords, 0, $params['count']);
			foreach ($slicedSubmissionsRecords as $submissionsRecord) {
				$publishedSubmissionDao = Application::getPublishedSubmissionDAO();
				$submission = $publishedSubmissionDao->getById($submissionsRecord['submission_id']);
				if ($submission) {
					$items[] = $statsService->getSummaryProperties($submission, $propertyArgs);
				}
			}
		}

		import('lib.pkp.controllers.stats.StatsComponentHandler');
		$statsHandler = new StatsComponentHandler(
			$dispatcher->url($request, ROUTE_API, $context->getPath(), 'stats/publishedSubmissions'),
			[
				'timeSegment' => 'day',
				'timeSegments' => $totalStats['timeSegments'],
				'items' => $items,
				'itemsMax' => count($submissionsRecords),
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
						'name' => 'totalFileViews',
						'label' => __('stats.fileViews'),
						'value' => 'totalFileViews',
					],
					[
						'name' => 'pdf',
						'label' => __('stats.pdf'),
						'value' => 'pdf',
					],
					[
						'name' => 'html',
						'label' => __('stats.html'),
						'value' => 'html',
					],
					[
						'name' => 'other',
						'label' => __('common.other'),
						'value' => 'other',
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

		$data = [
			'itemsMax' => count($submissionsRecords),
			'items' => $items,
		];
		$templateMgr->assign('statsComponent', $statsHandler);

		$templateMgr->display('stats/publishedSubmissions.tpl');
	}

	/**
	 * Display editorial report page
	 * @param $args array
	 * @param $request PKPRequest
	 */
	public function editorialReport(array $args, PKPRequest $request) : void
	{
		$dispatcher = $request->getDispatcher();
		$context = $request->getContext();
		$user = $request->getUser();

		if (!$context) {
			$dispatcher->handle404();
		}

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION
		);

		$templateMgr = TemplateManager::getManager($request);
		$this->setupTemplate($request);

		$params = [
			'dateStart' => new DateTimeImmutable('-31 days'),
			'dateEnd' => new DateTimeImmutable('yesterday')
		];

		import('lib.pkp.controllers.stats.EditorialReportComponentHandler');

		$editorialStatisticsService = \ServicesContainer::instance()->get('editorialStatistics');

		$statistics = $editorialStatisticsService->getSubmissionStatistics($context->getId());
		$rangedStatistics = $editorialStatisticsService->getSubmissionStatistics($context->getId(), $params);

		$userStatistics = $editorialStatisticsService->getUserStatistics($context->getId());
		$rangedUserStatistics = $editorialStatisticsService->getUserStatistics($context->getId(), $params);

		$submissions = $editorialStatisticsService->compileSubmissions($rangedStatistics, $statistics);
		$users = $editorialStatisticsService->compileUsers($rangedUserStatistics, $userStatistics);
		$activeSubmissions = $editorialStatisticsService->compileActiveSubmissions($statistics);
		$activeSubmissionsValues = array_values($activeSubmissions);

		$dateStart = $params['dateStart']->format('Y-m-d');
		$dateEnd = $params['dateEnd']->format('Y-m-d');
		$threeMonthsAgo = (new DateTimeImmutable('-91 days'))->format('Y-m-d');
		$oneYearAgo = (new DateTimeImmutable('-12 months'))->format('Y-m-d');

		$statsHandler = new EditorialReportComponentHandler(
			$dispatcher->url($request, ROUTE_API, $context->getPath(), 'stats/editorialReport'),
			[
				'submissionsStage' => $activeSubmissionsValues,
				'editorialChartData' => [
					'labels' => array_map(function ($stage) {
						return $stage['name'];
					}, $activeSubmissionsValues),
					'datasets' => [
						[
							'label' => __('stats.activeSubmissions'),
							'data' => array_map(function ($stage) {
								return $stage['value'];
							}, $activeSubmissionsValues),
							'backgroundColor' => array_map(function ($type) {
								switch ($type) {
									case EditorialStatisticsService::ACTIVE_SUBMISSIONS_ACTIVE:
										return '#d00a0a';
									case EditorialStatisticsService::ACTIVE_SUBMISSIONS_INTERNAL_REVIEW:
										return '#e05c14';
									case EditorialStatisticsService::ACTIVE_SUBMISSIONS_EXTERNAL_REVIEW:
										return '#e08914';
									case EditorialStatisticsService::ACTIVE_SUBMISSIONS_COPYEDITING:
										return '#007ab2';
									case EditorialStatisticsService::ACTIVE_SUBMISSIONS_PRODUCTION:
										return '#00b28d';
								}
								return '#' . substr(md5(rand()), 0, 6);
							}, array_keys($activeSubmissions))
						]
					],
				],
				'editorialItems' => array_values($submissions),
				'userItems' => array_values($users),
				'dateStart' => $dateStart,
				'dateEnd' => $dateEnd,
				'dateRangeOptions' => [
					[
						'dateStart' => $dateStart,
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last30Days'),
					],
					[
						'dateStart' => $threeMonthsAgo,
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last90Days'),
					],
					[
						'dateStart' => $oneYearAgo,
						'dateEnd' => $dateEnd,
						'label' => __('stats.dateRange.last12Months'),
					],
					[
						'dateStart' => '',
						'dateEnd' => '',
						'label' => __('stats.dateRange.allDates'),
					]
				]
			]
		);

		$templateMgr->assign('statsComponent', $statsHandler);
		$templateMgr->display('stats/editorialReport.tpl');
	}
}
