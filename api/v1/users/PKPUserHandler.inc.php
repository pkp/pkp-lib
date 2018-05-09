<?php

/**
 * @file api/v1/users/PKPUserHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2000-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPUserHandler
 * @ingroup api_v1_users
 *
 * @brief Base class to handle API requests for user operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.ServicesContainer');

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
					'handler' => array($this, 'getUsers'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/reviewers',
					'handler' => array($this, 'getReviewers'),
					'roles' => $roles
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{userId}',
					'handler' => array($this, 'getUser'),
					'roles' => $roles
				),
			),
			'POST' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{userId}/merge',
					'handler' => array($this, 'mergeUser'),
					'roles' => array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER),
				),
			),
		);
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	public function authorize($request, &$args, $roleAssignments) {

		// Only allow site admins to access site-wide endpoints
		if (!$request->getContext()) {
			$currentUser = $request->getUser();
			if (!$currentUser || !$currentUser->hasRole(ROLE_ID_SITE_ADMIN, CONTEXT_ID_NONE)) {
				return false;
			}
		}

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
	public function getUsers($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$context = $request->getContext();
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		$userService = ServicesContainer::instance()->get('user');

		$params = $this->_buildListRequestParams($slimRequest);

		$items = array();
		$users = $userService->getUsers($contextId, $params);
		if (!empty($users)) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
			);
			foreach ($users as $user) {
				$items[] = $userService->getSummaryProperties($user, $propertyArgs);
			}
		}

		$data = array(
			'itemsMax' => $userService->getUsersMaxCount($contextId, $params),
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
	public function getUser($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$userService = ServicesContainer::instance()->get('user');

		if (!empty($args['userId'])) {
			$user = $userService->getUser((int) $args['userId']);
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
		$contextId = $context ? $context->getId() : CONTEXT_ID_NONE;
		$userService = ServicesContainer::instance()->get('user');

		$params = $this->_buildReviewerListRequestParams($slimRequest);

		$items = array();
		$users = $userService->getReviewers($contextId, $params);
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
			'itemsMax' => $userService->getReviewersMaxCount($contextId, $params),
			'items' => $items,
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Merge a user into another
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 *
	 * @return Response
	 */
	public function mergeUser($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$currentUser = $request->getUser();
		$userService = ServicesContainer::instance()->get('user');
		$params = $slimRequest->getParsedBody();

		if (!$request->checkCSRF()) {
			return $response->withStatus(403)->withJsonError('api.403.csrfTokenFailure');
		}

		if (!empty($args['userId'])) {
			$user = $userService->getUser((int) $args['userId']);
		}

		if (!empty($params['mergeIntoUserId'])) {
			$mergeIntoUser = $userService->getUser((int) $params['mergeIntoUserId']);
		}

		if (empty($user) || empty($mergeIntoUser)) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if (!Validation::canAdminister($user->getId(), $currentUser->getId())) {
			return $response->withStatus(403)->withJsonError('api.users.403.unauthorizedAdminUser');
		}

		$userService->mergeUsers($user, $mergeIntoUser);

		$data = $userService->getFullProperties(
			$userService->getUser($mergeIntoUser->getId()),
			array(
				'request' => $request,
				'slimRequest' => $slimRequest
			)
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

				// Always convert contextIds, roleIds and userGroupIds to array
				case 'contextIds':
				case 'roleIds':
				case 'userGroupIds':
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = array($val);
					}
					$returnParams[$param] = array_map('intval', $val);
					break;

				case 'assignedToSubmissionStage':
				case 'assignedToSubmission':
				case 'assignedToSection':
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

		$returnParams = $this->_restrictContextIds($returnParams);

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
		$requestParams = $slimRequest->getQueryParams();

		foreach ($requestParams as $param => $val) {
			switch ($param) {

				case 'reviewerRating':
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

		// Restrict role IDs to reviewer roles
		$returnParams['roleIds'] = array(ROLE_ID_REVIEWER);

		$returnParams = $this->_restrictContextIds($returnParams);

		\HookRegistry::call('API::users::reviewers::params', array(&$returnParams, $slimRequest));

		return $returnParams;
	}

	/**
	 * Prevents use of the `contextIds` param except in site-wide requests
	 *
	 * @param $returnParams array The accepted params
	 * @return array
	 */
	private function _restrictContextIds($returnParams) {

		if ($this->getRequest()->getContext()) {
			unset($returnParams['contextIds']);
		}

		return $returnParams;
	}
}
