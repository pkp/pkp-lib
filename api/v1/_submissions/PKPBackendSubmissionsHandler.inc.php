<?php

/**
 * @file api/v1/_submissions/PKPBackendSubmissionsHandler.inc.php
 *
 * Copyright (c) 2014-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPBackendSubmissionsHandler
 * @ingroup api_v1_backend
 *
 * @brief Handle API requests for backend operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('lib.pkp.classes.submission.Submission');
import('classes.core.ServicesContainer');

abstract class PKPBackendSubmissionsHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$rootPattern = '/{contextPath}/api/{version}/_submissions';
		$this->_endpoints = array_merge_recursive($this->_endpoints, array(
			'GET' => array(
				array(
					'pattern' => "{$rootPattern}",
					'handler' => array($this, 'getSubmissions'),
					'roles' => array(
						ROLE_ID_SITE_ADMIN,
						ROLE_ID_MANAGER,
						ROLE_ID_SUB_EDITOR,
						ROLE_ID_AUTHOR,
						ROLE_ID_REVIEWER,
						ROLE_ID_ASSISTANT,
					),
				),
			),
			'DELETE' => array(
				array(
					'pattern' => "{$rootPattern}/{submissionId}",
					'handler' => array($this, 'deleteSubmission'),
					'roles' => array(
						ROLE_ID_SITE_ADMIN,
						ROLE_ID_MANAGER,
						ROLE_ID_AUTHOR,
					),
				),
			),
		));
		parent::__construct();
	}

	/**
	 * Get a list of submissions according to passed query parameters
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 *
	 * @return Response
	 */
	public function getSubmissions($slimRequest, $response, $args) {

		$request = $this->getRequest();
		$currentUser = $request->getUser();
		$context = $request->getContext();

		// Merge query params over default params
		$defaultParams = array(
			'count' => 20,
			'offset' => 0,
		);

		$params = array_merge($defaultParams, $slimRequest->getQueryParams());

		// Anyone not a manager or site admin can only access their assigned
		// submissions
		if (!$currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $context->getId())) {
			$defaultParams['assignedTo'] = $currentUser->getId();
		}

		// Process query params to format incoming data as needed
		foreach ($params as $param => $val) {
			switch ($param) {

				// Always convert status and stageIds to array
				case 'status':
				case 'stageIds':
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = array($val);
					}
					$params[$param] = array_map('intval', $val);
					break;

				case 'assignedTo':
					$params[$param] = (int) $val;
					break;

				// Enforce a maximum count to prevent the API from crippling the
				// server
				case 'count':
					$params[$param] = min(100, (int) $val);
					break;

				case 'offset':
					$params[$param] = (int) $val;
					break;

				case 'orderBy':
					if (!in_array($val, array('dateSubmitted', 'lastModified', 'title'))) {
						unset($params[$param]);
					}
					break;

				case 'orderDirection':
					$params[$param] = $val === 'ASC' ? $val : 'DESC';
					break;

				case 'isIncomplete':
				case 'isOverdue':
					$params[$param] = true;
			}
		}

		// Prevent users from viewing submissions they're not assigned to,
		// except for journal managers and admins.
		if (!$currentUser->hasRole(array(ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN), $context->getId())
				&& $params['assignedTo'] != $currentUser->getId()) {
			return $response->withStatus(403)->withJsonError('api.submissions.403.requestedOthersUnpublishedSubmissions');
		}

		\HookRegistry::call('API::_submissions::params', array(&$params, $slimRequest, $response));

		$submissionService = ServicesContainer::instance()->get('submission');
		$submissions = $submissionService->getSubmissions($context->getId(), $params);
		$items = array();
		if (!empty($submissions)) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
			);
			foreach ($submissions as $submission) {
				$items[] = $submissionService->getBackendListProperties($submission, $propertyArgs);
			}
		}
		$data = array(
			'items' => $items,
			'itemsMax' => $submissionService->getSubmissionsMaxCount($context->getId(), $params),
		);

		return $response->withJson($data);
	}

	/**
	 * Delete a submission
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function deleteSubmission($slimRequest, $response, $args) {

		$request = Application::getRequest();
		$currentUser = $request->getUser();
		$context = $request->getContext();

		$submissionId = (int) $args['submissionId'];

		$submissionDao = Application::getSubmissionDAO();
		$submission = $submissionDao->getById($submissionId);

		if (!$submission) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.resourceNotFound');
		}

		if ($context->getId() != $submission->getContextId()) {
			return $response->withStatus(403)->withJsonError('api.submissions.403.deleteSubmissionOutOfContext');
		}

		import('classes.core.ServicesContainer');
		$submissionService = ServicesContainer::instance()
				->get('submission');

		if (!$submissionService->canCurrentUserDelete($submission)) {
			return $response->withStatus(403)->withJsonError('api.submissions.403.unauthorizedDeleteSubmission');
		}

		$submissionService->deleteSubmission($submissionId);

		return $response->withJson(true);
	}
}
