<?php

/**
 * @file api/v1/users/PKPUserHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2000-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 * @ingroup api_v1_users
 *
 * @brief Base class to handle API requests for user operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPUserHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'users';
		$roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR);
		$this->_endpoints = array(
			'GET' => array (
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'getMany'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/reviewers',
					'handler' => array($this, 'getReviewers'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{userId}',
					'handler' => array($this, 'get'),
					'roles' => $roles
				),
			),
		);
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get a collection of users
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 *
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$params = $this->_processAllowedParams($slimRequest->getQueryParams(), [
			'assignedToSubmission',
			'assignedToSubmissionStage',
			'count',
			'offset',
			'orderBy',
			'orderDirection',
			'roleIds',
			'searchPhrase',
			'status',
		]);

		$params['contextId'] = $context->getId();

		\HookRegistry::call('API::users::params', [&$params, $slimRequest]);

		$items = [];
		$usersItereator = Services::get('user')->getMany($params);
		if (count($usersItereator)) {
			$propertyArgs = [
				'request' => $request,
				'slimRequest' => $slimRequest,
			];
			foreach ($usersItereator as $user) {
				$items[] = Services::get('user')->getSummaryProperties($user, $propertyArgs);
			}
		}

		$data = [
			'itemsMax' => Services::get('user')->getMax($params),
			'items' => $items,
		];

		return $response->withJson($data, 200);
	}

	/**
	 * Get a single user
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 *
	 * @return Response
	 */
	public function get($slimRequest, $response, $args) {
		$request = $this->getRequest();

		if (!empty($args['userId'])) {
			$user = Services::get('user')->get((int) $args['userId']);
		}

		if (!$user) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$data = Services::get('user')->getFullProperties($user, array(
			'request' => $request,
			'slimRequest' 	=> $slimRequest
		));

		return $response->withJson($data, 200);
	}

	/**
	 * Get a collection of reviewers
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 *
	 * @return Response
	 */
	public function getReviewers($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$params = $this->_processAllowedParams($slimRequest->getQueryParams(), [
			'averageCompletion',
			'count',
			'daysSinceLastAssignment',
			'offset',
			'orderBy',
			'orderDirection',
			'reviewerRating',
			'reviewsActive',
			'reviewsCompleted',
			'reviewStage',
			'searchPhrase',
			'status',
		]);

		$params['contextId'] = $context->getId();

		\HookRegistry::call('API::users::reviewers::params', array(&$params, $slimRequest));

		$items = [];
		$usersIterator = Services::get('user')->getReviewers($params);
		if (count($usersIterator)) {
			$propertyArgs = [
				'request' => $request,
				'slimRequest' => $slimRequest,
			];
			foreach ($usersIterator as $user) {
				$items[] = Services::get('user')->getReviewerSummaryProperties($user, $propertyArgs);
			}
		}

		$data = array(
			'itemsMax' => Services::get('user')->getReviewersMax($params),
			'items' => $items,
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Convert the query params passed to the end point. Exclude unsupported
	 * params and coerce the type of those passed.
	 *
	 * @param array $params Key/value of request params
	 * @param array $allowedKeys The param keys which should be processed and returned
	 * @return array
	 */
	private function _processAllowedparams($params, $allowedKeys) {

		// Merge query params over default params
		$defaultParams = [
			'count' => 20,
			'offset' => 0,
		];

		$requestParams = array_merge($defaultParams, $params);

		// Process query params to format incoming data as needed
		$returnParams = [];
		foreach ($requestParams as $param => $val) {
			if (!in_array($param, $allowedKeys)) {
				continue;
			}
			switch ($param) {
				case 'orderBy':
					if (in_array($val, ['id', 'familyName', 'givenName'])) {
						$returnParams[$param] = $val;
					}
					break;

				case 'orderDirection':
					$returnParams[$param] = $val === 'ASC' ? $val : 'DESC';
					break;

				case 'status':
					if (in_array($val, ['all', 'active', 'disabled'])) {
						$returnParams[$param] = $val;
					}
					break;

				// Always convert roleIds to array
				case 'roleIds':
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = [$val];
					}
					$returnParams[$param] = array_map('intval', $val);
					break;

				case 'assignedToSubmissionStage':
				case 'assignedToSubmission':
				case 'reviewerRating':
				case 'reviewStage':
				case 'offset':
					$returnParams[$param] = (int) $val;
					break;

				case 'searchPhrase':
					$returnParams[$param] = trim($val);
					break;

				case 'reviewsCompleted':
				case 'reviewsActive':
				case 'daysSinceLastAssignment':
				case 'averageCompletion':
					if (strpos($val, '-') !== false) {
						$val = array_map('intval', explode('-', $val));
					} else {
						$val = (int) $val;
					}
					$returnParams[$param] = $val;
					break;

				// Enforce a maximum count per request
				case 'count':
					$returnParams[$param] = min(100, (int) $val);
					break;
			}
		}

		return $returnParams;
	}
}
