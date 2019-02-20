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

class PKPStatsHandler extends Handler {
	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->addRoleAssignment(
			[ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
			array('publishedSubmissions')
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
	 * @param $request PKPRequest
	 * @param $args array
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
			$propertyArgs = array(
				'request' => $request,
				'params' => $params
			);
			$slicedSubmissionsRecords = array_slice($submissionsRecords, 0, $params['count']);
			foreach ($slicedSubmissionsRecords as $submissionsRecord) {
				$publishedSubmissionDao = Application::getPublishedSubmissionDAO();
				$submission = $publishedSubmissionDao->getById($submissionsRecord['submission_id']);
				$items[] = $statsService->getSummaryProperties($submission, $propertyArgs);
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

		$data = array(
			'itemsMax' => count($submissionsRecords),
			'items' => $items,
		);

		$templateMgr->assign('statsComponent', $statsHandler);

		$templateMgr->display('stats/publishedSubmissions.tpl');
	}
}
