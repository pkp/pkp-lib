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

class PKPStatsHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'stats';
		$roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER);
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => $this->getEndpointPattern() . '/publishedSubmissions',
					'handler' => array($this, 'getSubmissionList'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/publishedSubmissions/{submissionId}',
					'handler' => array($this, 'getSubmission'),
					'roles' => $roles
				),
			),
		);
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
		$defaultParams = array(
			'count' => 30,
			'offset' => 0,
			'timeSegment' => 'month'
		);
		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$params = array();
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
						$val = array($val);
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

		\HookRegistry::call('API::stats::publishedSubmissions::params', array(&$params, $slimRequest));

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

		$data = $items = array();
		if (!empty($submissionsRecords)) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
				'params' => $params
			);
			// get total stats data
			$totalStatsRecords = $statsService->getTotalSubmissionsStats($context->getId(), $params);
			$data = $statsService->getTotalStatsProperties($totalStatsRecords, $propertyArgs);
			// get submisisons stats items
			$currentPageSubmissionsRecords = array_slice($submissionsRecords, isset($params['offset']) ? $params['offset'] : 0, $params['count']);
			foreach ($currentPageSubmissionsRecords as $submissionsRecord) {
				$publishedSubmissionDao = \Application::getPublishedSubmissionDAO();
				$submission = $publishedSubmissionDao->getByArticleId($submissionsRecord['submission_id'], $context->getId());
				if ($submission) {
					$items[] = $statsService->getSummaryProperties($submission, $propertyArgs);
				}
			}
		} else {
			$data = array(
				'abstractViews' => 0,
				'totalFileViews' => 0,
				'timeSegments' => array()
			);
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
		$defaultParams = array(
			'timeSegment' => 'month'
		);
		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$params = array();
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

		\HookRegistry::call('API::stats::publishedSubmission::params', array(&$params, $slimRequest));

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
			->getFullProperties($submission, array(
				'request' => $request,
				'slimRequest' 	=> $slimRequest,
				'params' => $params
			));

		return $response->withJson($data, 200);
	}

	/**
	 * Has the given date valid fromat YYYY-MM-DD
	 * @param $dateString string
	 * @return boolean
	 */
	public function validDate($dateString) {
		return preg_match('/(\d{4})-(\d{2})-(\d{2})/', $dateString, $matches) === 1;
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
