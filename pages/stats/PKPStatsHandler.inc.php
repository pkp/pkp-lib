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
			array('publications')
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

		$statsComponent = new \PKP\components\PKPStatsComponent(
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
}
