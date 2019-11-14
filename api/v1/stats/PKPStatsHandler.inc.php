<?php

/**
 * @file api/v1/stats/PKPStatsHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for statistics operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.ServicesContainer');

use \PKP\Services\EditorialStatisticsService;

class PKPStatsHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'stats';
		$roles = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER];
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern() . '/publishedSubmissions',
					'handler' => [$this, 'getSubmissionList'],
					'roles' => $roles
				],
				[
					'pattern' => $this->getEndpointPattern() . '/publishedSubmissions/{submissionId}',
					'handler' => [$this, 'getSubmission'],
					'roles' => $roles
				],
				[
					'pattern' => $this->getEndpointPattern() . '/editorialReport',
					'handler' => [$this, 'getEditorialReport'],
					'roles' => $roles
				]
			]
		];
		parent::__construct();
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		$routeName = null;
		$slimRequest = $this->getSlimRequest();

		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

		if (!is_null($slimRequest) && ($route = $slimRequest->getAttribute('route'))) {
			$routeName = $route->getName();
		}
		if ($routeName === 'getSubmission') {
			import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
			$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get total stats and a collection of submissions
	 * @param $slimRequest Request Slim request object
	 * @param $response object Response
	 * @param $args array
	 * @return object Response
	 */
	public function getSubmissionList($slimRequest, $response, $args) {
		$request = \Application::getRequest();
		$context = $request->getContext();

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.resourceNotFound');
		}

		// Convert params passed to the api end point
		// Merge query params over default params
		$defaultParams = [
			'count' => 30,
			'offset' => 0,
			'timeSegment' => 'month'
		];
		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$params = [];
		// Process query params to format incoming data as needed
		foreach ($requestParams as $param => $val) {
			switch ($param) {
				case 'orderBy':
					$params[$param] = 'total';
					break;
				case 'orderDirection':
					$params[$param] = $val === 'ASC' ? $val : 'DESC';
					break;
					// Enforce a maximum count to prevent the API from crippling the
					// server
				case 'count':
					$params[$param] = min(100, (int) $val);
					break;
				case 'offset':
					$params[$param] = (int) $val;
					break;
				case 'sectionIds':
					// these are section IDs in OJS and series IDs in OMP
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = [$val];
					}
					$params[$param] = array_map('intval', $val);
					break;
				case 'timeSegment':
				case 'dateStart':
				case 'dateEnd':
				case 'searchPhrase':
					$params[$param] = $val;
					break;
			}
		}

		\HookRegistry::call('API::stats::publishedSubmissions::params', [&$params, $slimRequest]);

		// validate parameters
		if (isset($params['dateStart'])) {
			if (!$this->validDate($params['dateStart'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateFormat');
			}
		}
		if (isset($params['dateEnd'])) {
			if (!$this->validDate($params['dateEnd'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateFormat');
			}
		}
		if (isset($params['dateStart']) && isset($params['dateEnd'])) {
			if (!$this->validDateRange($params['dateStart'], $params['dateEnd'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateRange');
			}
		}
		if (isset($params['timeSegment']) && $params['timeSegment'] == 'day') {
			if (isset($params['dateStart'])) {
				if (!$this->dateWithinLast90Days($params['dateStart'])) {
					return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimeSegmentDaily');
				}
			} else {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimeSegmentDaily');
			}
		}

		$statsService = \ServicesContainer::instance()->get('stats');
		$submissionsRecords = $statsService->getOrderedSubmissions($context->getId(), $params);

		$data = $items = [];
		if (!empty($submissionsRecords)) {
			$propertyArgs = [
				'request' => $request,
				'slimRequest' => $slimRequest,
				'params' => $params
			];
			// get total stats data
			$totalStatsRecords = $statsService->getTotalSubmissionsStats($context->getId(), $params);
			$data = $statsService->getTotalStatsProperties($totalStatsRecords, $propertyArgs);
			// get submissions stats items
			$currentPageSubmissionsRecords = array_slice($submissionsRecords, isset($params['offset']) ? $params['offset'] : 0, $params['count']);
			foreach ($currentPageSubmissionsRecords as $submissionsRecord) {
				$publishedSubmissionDao = \Application::getPublishedSubmissionDAO();
				$submission = $publishedSubmissionDao->getByArticleId($submissionsRecord['submission_id'], $context->getId());
				if ($submission) {
					$items[] = $statsService->getSummaryProperties($submission, $propertyArgs);
				}
			}
		} else {
			$data = [
				'abstractViews' => 0,
				'totalFileViews' => 0,
				'timeSegments' => []
			];
		}
		$data['itemsMax'] = count($submissionsRecords);
		$data['items'] = $items;

		return $response->withJson($data, 200);
	}

	/**
	 * Get a single submission's usage statistics
	 * @param $slimRequest object Request Slim request
	 * @param $response object Response
	 * @param $args array
	 * @return object Response
	 */
	public function getSubmission($slimRequest, $response, $args) {
		$request = \Application::getRequest();

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		// Convert params passed to the api
		// Merge query params over default params
		$defaultParams = [
			'timeSegment' => 'month'
		];
		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$params = [];
		// Process query params to format incoming data as needed
		foreach ($requestParams as $param => $val) {
			switch ($param) {
				case 'timeSegment':
				case 'dateStart':
				case 'dateEnd':
					$params[$param] = $val;
					break;
			}
		}

		\HookRegistry::call('API::stats::publishedSubmission::params', [&$params, $slimRequest]);

		// validate parameters
		if (isset($params['dateStart'])) {
			if (!$this->validDate($params['dateStart'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateFormat');
			}
		}
		if (isset($params['dateEnd'])) {
			if (!$this->validDate($params['dateEnd'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateFormat');
			}
		}
		if (isset($params['dateStart']) && isset($params['dateEnd'])) {
			if (!$this->validDateRange($params['dateStart'], $params['dateEnd'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateRange');
			}
		}
		if (isset($params['timeSegment']) && $params['timeSegment'] == 'day') {
			if (isset($params['dateStart'])) {
				if (!$this->dateWithinLast90Days($params['dateStart'])) {
					return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimeSegmentDaily');
				}
			} else {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongTimeSegmentDaily');
			}
		}

		$data = ServicesContainer::instance()
			->get('stats')
			->getFullProperties($submission, [
				'request' => $request,
				'slimRequest' => $slimRequest,
				'params' => $params
			]);

		return $response->withJson($data, 200);
	}

	/**
	 * Retrieve an overview of all the active submissions, including yearly statistics
	 * @param $slimRequest \Slim\Http\Request Request Slim request
	 * @param $response APIResponse Response
	 * @param $args array
	 * @return APIResponse Response
	 */
	public function getEditorialReport(\Slim\Http\Request $slimRequest, APIResponse $response, array $args) : APIResponse
	{
		$request = \Application::getRequest();
		$context = $request->getContext();

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.resourceNotFound');
		}

		AppLocale::requireComponents(
			LOCALE_COMPONENT_PKP_USER,
			LOCALE_COMPONENT_PKP_MANAGER,
			LOCALE_COMPONENT_APP_MANAGER,
			LOCALE_COMPONENT_PKP_SUBMISSION,
			LOCALE_COMPONENT_APP_SUBMISSION
		);

		$params = [];
		// Process query params to format incoming data as needed
		foreach ($slimRequest->getQueryParams() as $param => $val) {
			switch ($param) {
				case 'dateStart':
				case 'dateEnd':
					$params[$param] = $val;
					break;
				case 'sectionIds':
					// these are section IDs in OJS and series IDs in OMP
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = [$val];
					}
					$params[$param] = array_map('intval', $val);
					break;
			}
		}

		\HookRegistry::call('API::stats::editorialReport::params', [&$params, $slimRequest]);

		// validate parameters
		if (isset($params['dateStart'])) {
			if (!$this->validDate($params['dateStart'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateFormat');
			}
			$params['dateStart'] = new \DateTimeImmutable($params['dateStart']);
		}
		if (isset($params['dateEnd'])) {
			if (!$this->validDate($params['dateEnd'])) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateFormat');
			}
			$params['dateEnd'] = new \DateTimeImmutable($params['dateEnd']);
		}
		if (isset($params['dateStart']) && isset($params['dateEnd'])) {
			if (!$this->validDateRange($params['dateStart']->format('c'), $params['dateEnd']->format('c'))) {
				return $response->withStatus(400)->withJsonError('api.stats.400.wrongDateRange');
			}
		}

		$paramsWithoutDateRange = array_diff_key($params, ['dateStart' => null, 'dateEnd' => null]);

		$editorialStatisticsService = \ServicesContainer::instance()->get('editorialStatistics');

		$statistics = $editorialStatisticsService->getSubmissionStatistics($context->getId(), $paramsWithoutDateRange);
		$rangedStatistics = $editorialStatisticsService->getSubmissionStatistics($context->getId(), $params);

		$userStatistics = $editorialStatisticsService->getUserStatistics($context->getId(), $paramsWithoutDateRange);
		$rangedUserStatistics = $editorialStatisticsService->getUserStatistics($context->getId(), $params);

		$submissions = $editorialStatisticsService->compileSubmissions($rangedStatistics, $statistics);
		$users = $editorialStatisticsService->compileUsers($rangedUserStatistics, $userStatistics);
		$activeSubmissions = $editorialStatisticsService->compileActiveSubmissions($statistics);
		$activeSubmissionsValues = array_values($activeSubmissions);

		return $response->withJson([
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
			'userItems' => array_values($users)
		], 200);
	}

	/**
	 * Has the given date valid format YYYY-MM-DD
	 * @param $dateString string
	 * @return boolean
	 */
	public function validDate($dateString) {
		return !!DateTimeImmutable::createFromFormat('Y-m-d', $dateString);
	}

	/**
	 * Is the given date range valid
	 * @param $dateStart string
	 * @param $dateEnd string
	 * @return boolean
	 */
	public function validDateRange($dateStart, $dateEnd) {
		$dateStartTimestamp = strtotime($dateStart);
		$dateEndTimestamp = strtotime($dateEnd);
		return $dateStartTimestamp <= $dateEndTimestamp;
	}

	/**
	 * Is the given date withing the last 90 days
	 * @param $dateString string
	 * @return boolean
	 */
	public function dateWithinLast90Days($dateString) {
		$dateTimestamp = strtotime($dateString);
		// 90 days + 1 day because the most recent allowed date is yesterday
		// + 1 more day to account for the fact that the $dateTimestamp begins at
		// the start of the day
		$lastNinetyDaysTimestamp = strtotime('-92 days');
		return $dateTimestamp >= $lastNinetyDaysTimestamp;
	}
}
