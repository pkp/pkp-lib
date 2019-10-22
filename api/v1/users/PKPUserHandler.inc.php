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
		$userService = Services::get('user');

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$params = $this->_buildListRequestParams($slimRequest);

		$items = array();
		$result = $userService->getMany($params);
		if ($result->valid()) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
			);
			foreach ($result as $user) {
				$items[] = $userService->getSummaryProperties($user, $propertyArgs);
			}
		}

		$data = array(
			'itemsMax' => $userService->getMax($params),
			'items' => $items,
		);

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
		$context = $request->getContext();
		$userService = Services::get('user');

		if (!empty($args['userId'])) {
			$user = $userService->get((int) $args['userId']);
		}

		if (!$user) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$data = $userService->getFullProperties($user, array(
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
		$userService = Services::get('user');

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		// We do not support these params from /users
		if (isset($returnParams['assignedToSubmission'])) {
			return $response->withStatus(400)->withJsonError('api.400.paramNotSupported', 'assignedToSubmission');
		}
		if (isset($returnParams['assignedToSubmissionStage'])) {
			return $response->withStatus(400)->withJsonError('api.400.paramNotSupported', 'assignedToSubmissionStage');
		}
		if (isset($returnParams['roleIds'])) {
			return $response->withStatus(400)->withJsonError('api.400.paramNotSupported', 'roleIds');
		}

		$params = $this->_buildReviewerListRequestParams($slimRequest);

		$items = array();
		$users = $userService->getReviewers($params);
		if (!empty($users)) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
			);
			foreach ($users as $user) {
				$items[] = $userService->getReviewerSummaryProperties($user, $propertyArgs);
			}
		}

		$data = array(
			'itemsMax' => $userService->getReviewersMax($params),
			'items' => $items,
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Convert params passed to list requests. Coerce type and only return
	 * white-listed params.
	 *
	 * @param $slimRequest Request Slim request object
	 * @return array
	 */
	private function _buildListRequestParams($slimRequest) {

		$request = $this->getRequest();
		$currentUser = $request->getUser();
		$context = $request->getContext();

		// Merge query params over default params
		$defaultParams = array(
			'count' => 20,
			'offset' => 0,
		);

		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$returnParams = array();

		// Process query params to format incoming data as needed
		foreach ($requestParams as $param => $val) {
			switch ($param) {

				case 'orderBy':
					if (in_array($val, array('id', 'familyName', 'givenName'))) {
						$returnParams[$param] = $val;
					}
					break;

				case 'orderDirection':
					$returnParams[$param] = $val === 'ASC' ? $val : 'DESC';
					break;

				case 'status':
					if (in_array($val, array('all', 'active', 'disabled'))) {
						$returnParams[$param] = $val;
					}
					break;

				// Always convert roleIds to array
				case 'roleIds':
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = array($val);
					}
					$returnParams[$param] = array_map('intval', $val);
					break;

				case 'assignedToSubmissionStage':
				case 'assignedToSubmission':
					$returnParams[$param] = (int) $val;
					break;

				case 'searchPhrase':
					$returnParams[$param] = trim($val);
					break;

				// Enforce a maximum count to prevent the API from crippling the
				// server
				case 'count':
					$returnParams[$param] = min(100, (int) $val);
					break;

				case 'offset':
					$returnParams[$param] = (int) $val;
					break;
			}
		}

		$returnParams['contextId'] = $context->getId();

		\HookRegistry::call('API::users::params', array(&$returnParams, $slimRequest));

		return $returnParams;
	}

	/**
	 * Add reviewer-specific params
	 *
	 * @param $slimRequest Request Slim request object
	 * @return array
	 */
	private function _buildReviewerListRequestParams($slimRequest) {

		$returnParams = $this->_buildListRequestParams($slimRequest);
		$contextId = $returnParams['contextId'];
		$requestParams = $slimRequest->getQueryParams();

		foreach ($requestParams as $param => $val) {
			switch ($param) {

				case 'reviewerRating':
				case 'reviewStage':
					$returnParams[$param] = (int) $val;
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
			}
		}

		// Don't allow the contextId to be overridden
		$returnParams['contextId'] = $contextId;

		\HookRegistry::call('API::users::reviewers::params', array(&$returnParams, $slimRequest));

		return $returnParams;
	}
}
