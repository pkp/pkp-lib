<?php

/**
 * @file api/v1/submissions/PKPSubmissionHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionHandler
 * @ingroup api_v1_submission
 *
 * @brief Handle API requests for submission operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPSubmissionHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'submissions';
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'getMany'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}',
					'handler' => [$this, 'get'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/participants',
					'handler' => [$this, 'getParticipants'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/participants/{stageId}',
					'handler' => [$this, 'getParticipants'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications',
					'handler' => [$this, 'getPublications'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}',
					'handler' => [$this, 'getPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT, ROLE_ID_REVIEWER, ROLE_ID_AUTHOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}/publish',
					'handler' => [$this, 'publishPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
			],
			'POST' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'add'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications',
					'handler' => [$this, 'addPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}/version',
					'handler' => [$this, 'versionPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
			],
			'PUT' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}',
					'handler' => [$this, 'edit'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}',
					'handler' => [$this, 'editPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}/publish',
					'handler' => [$this, 'publishPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}/unpublish',
					'handler' => [$this, 'unpublishPublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
			],
			'DELETE' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}',
					'handler' => [$this, 'delete'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{submissionId}/publications/{publicationId}',
					'handler' => [$this, 'deletePublication'],
					'roles' => [ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT],
				],
			],
		];
		parent::__construct();
	}

	//
	// Implement methods from PKPHandler
	//
	function authorize($request, &$args, $roleAssignments) {
		$routeName = $this->getSlimRequest()->getAttribute('route')->getName();

		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

		$requiresSubmissionAccess = [
			'get',
			'edit',
			'delete',
			'getGalleys',
			'getParticipants',
			'getPublications',
			'getPublication',
			'addPublication',
			'versionPublication',
			'editPublication',
			'checkPublishRequirements',
			'publishPublication',
			'unpublishPublication',
			'deletePublication',
		];
		if (in_array($routeName, $requiresSubmissionAccess)) {
			import('lib.pkp.classes.security.authorization.SubmissionAccessPolicy');
			$this->addPolicy(new SubmissionAccessPolicy($request, $args, $roleAssignments));
		}

		$requiresPublicationWriteAccess = [
			'addPublication',
			'editPublication',
			'checkPublishRequirements',
		];
		if (in_array($routeName, $requiresPublicationWriteAccess)) {
			import('lib.pkp.classes.security.authorization.PublicationWritePolicy');
			$this->addPolicy(new PublicationWritePolicy($request, $args, $roleAssignments));
		}

		$requiresProductionStageAccess = [
			'versionPublication',
			'publishPublication',
			'unpublishPublication',
			'deletePublication',
		];
		if (in_array($routeName, $requiresProductionStageAccess)) {
			import('lib.pkp.classes.security.authorization.StageRolePolicy');
			$this->addPolicy(new StageRolePolicy([ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT], WORKFLOW_STAGE_ID_PRODUCTION));
		}

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get a collection of submissions
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = Application::get()->getRequest();
		$currentUser = $request->getUser();
		$context = $request->getContext();
		$submissionService = Services::get('submission');

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$params = $this->_buildListRequestParams($slimRequest);

		// Prevent users from viewing submissions they're not assigned to,
		// except for journal managers and admins.
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$canAccessUnassignedSubmission = !empty(array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $userRoles));
		if (!$canAccessUnassignedSubmission && $params['assignedTo'] != $currentUser->getId()) {
			return $response->withStatus(403)->withJsonError('api.submissions.403.requestedOthersUnpublishedSubmissions');
		}

		$items = array();
		$submissions = $submissionService->getMany($params);
		if (!empty($submissions)) {
			$propertyArgs = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($context->getId())->toArray()
			);
			foreach ($submissions as $submission) {
				$items[] = $submissionService->getSummaryProperties($submission, $propertyArgs);
			}
		}

		$data = array(
			'itemsMax' => $submissionService->getMax($params),
			'items' => $items,
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Get a single submission
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function get($slimRequest, $response, $args) {
		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_READER, LOCALE_COMPONENT_PKP_SUBMISSION);

		$request = Application::get()->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$data = Services::get('submission')->getFullProperties($submission, array(
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
		));

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

		$request = Application::get()->getRequest();
		$currentUser = $request->getUser();

		// Merge query params over default params
		$defaultParams = array(
			'count' => 20,
			'offset' => 0,
		);

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		$canAccessUnassignedSubmission = !empty(array_intersect(array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER), $userRoles));
		if (!$canAccessUnassignedSubmission) {
			$defaultParams['assignedTo'] = $currentUser->getId();
		}

		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$returnParams = array();

		// Process query params to format incoming data as needed
		foreach ($requestParams as $param => $val) {
			switch ($param) {

				case 'orderBy':
					if (in_array($val, array('dateSubmitted', 'lastModified', 'title'))) {
						$returnParams[$param] = $val;
					}
					break;

				case 'orderDirection':
					$returnParams[$param] = $val === 'ASC' ? $val : 'DESC';
					break;

				// Always convert status and stageIds to array
				case 'status':
				case 'stageIds':
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = array($val);
					}
					$returnParams[$param] = array_map('intval', $val);
					break;

				case 'assignedTo':
				case 'daysInactive':
				case 'offset':
					$returnParams[$param] = (int) $val;
					break;

				case 'searchPhrase':
					$returnParams[$param] = $val;
					break;

				// Enforce a maximum count to prevent the API from crippling the
				// server
				case 'count':
					$returnParams[$param] = min(100, (int) $val);
					break;

				case 'isIncomplete':
				case 'isOverdue':
					$returnParams[$param] = true;
					break;
			}
		}

		$returnParams['contextId'] = $request->getContext()->getId();

		\HookRegistry::call('API::submissions::params', array(&$returnParams, $slimRequest));

		return $returnParams;
	}

	/**
	 * Add a new submission
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function add($slimRequest, $response, $args) {
		$request = $this->getRequest();

		// Don't allow submissions to be added via the site-wide API
		if (!$request->getContext()) {
			return $response->withStatus(400)->withJsonError('api.submissions.403.contextRequired');
		}

		$params = $this->convertStringsToSchema(SCHEMA_SUBMISSION, $slimRequest->getParsedBody());
		$params['contextId'] = $request->getContext()->getId();

		$primaryLocale = $request->getContext()->getPrimaryLocale();
		$allowedLocales = $request->getContext()->getSupportedLocales();

		$errors = Services::get('submission')->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$submission = Application::get()->getSubmissionDAO()->newDataObject();
		$submission->_data = $params;
		$submission = Services::get('submission')->add($submission, $request);

		$data = Services::get('submission')->getFullProperties($submission, [
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Edit a submission
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function edit($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		if (!$submission) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		// Don't allow submissions to be added via the site-wide API
		if (!$request->getContext()) {
			return $response->withStatus(400)->withJsonError('api.submissions.403.contextRequired');
		}

		$params = $this->convertStringsToSchema(SCHEMA_SUBMISSION, $slimRequest->getParsedBody());
		$params['id'] = $submission->getId();
		$params['contextId'] = $request->getContext()->getId();

		// Don't allow a submission's context to be changed
		if (isset($params['contextId']) && $params['contextId'] !== $submission->getData('contextId')) {
			return $response->withStatus(403)->withJsonError('api.submissions.403.cantChangeContext');
		}

		$primaryLocale = $request->getContext()->getPrimaryLocale();
		$allowedLocales = $request->getContext()->getSupportedLocales();

		$errors = Services::get('submission')->validate(VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$submission = Services::get('submission')->edit($submission, $params, $request);

		$data = Services::get('submission')->getFullProperties($submission, [
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Delete a submission
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function delete($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		if (!$submission) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$submissionProps = Services::get('submission')->getFullProperties($submission, [
			'request' => $request,
			'slimRequest' 	=> $slimRequest,
			'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
		]);

		Services::get('submission')->delete($submission);

		return $response->withJson($submissionProps, 200);
	}

	/**
	 * Get the participants assigned to a submission
	 *
	 * This does not return reviewers.
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getParticipants($slimRequest, $response, $args) {
		$request = Application::get()->getRequest();
		$context = $request->getContext();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$stageId = isset($args['stageId']) ? $args['stageId'] : null;

		if (!$submission) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$data = array();

		$userService = Services::get('user');

		$users = $userService->getMany(array(
			'contextId' => $context->getId(),
			'count' => 100, // high upper-limit
			'assignedToSubmission' => $submission->getId(),
			'assignedToSubmissionStage' => $stageId,
		));
		if (!empty($users)) {
			$args = array(
				'request' => $request,
				'slimRequest' => $slimRequest,
			);
			foreach ($users as $user) {
				$data[] = $userService->getSummaryProperties($user, $args);
			}
		}

		return $response->withJson($data, 200);
	}


	/**
	 * Get all of this submissions's publications
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 * @return Response
	 */
	public function getPublications($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$allowedParams = ['submissionIds' => $submission->getId(), 'count' => 1000];

		$userGroups = DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray();

		$items = array_map(
			function($publication) use ($request, $userGroups) {
				return Services::get('publication')->getSummaryProperties($publication, ['request' => $request, 'userGroups' => $userGroups]);
			},
			Services::get('publication')->getMany($allowedParams)
		);

		$data = [
			'itemsMax' => Services::get('publication')->getMax($allowedParams),
			'items' => $items,
		];

		return $response->withJson($data, 200);
	}

	/**
	 * Get one of this submission's publications
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getPublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$publication = Services::get('publication')->get((int) $args['publicationId']);

		if (!$publication) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if ($submission->getId() !== $publication->getData('submissionId')) {
			return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
		}

		$data = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Add a new publication to this submission
	 *
	 * This will create a new publication from scratch. If you want to create a new
	 * version of a publication, see self::versionPublication().
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function addPublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);

		$params = $this->convertStringsToSchema(SCHEMA_PUBLICATION, $slimRequest->getParsedBody());
		$params['submissionId'] = $submission->getId();

		$submissionContext = $request->getContext();
		if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
			$submissionContext = Services::get('context')->get($submission->getData('contextId'));
		}
		$primaryLocale = $submissionContext->getPrimaryLocale();
		$allowedLocales = $submissionContext->getSupportedLocales();

		$errors = Services::get('publication')->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$publication = DAORegistry::getDAO('PublicationDAO')->newDataObject();
		$publication->_data = $params;
		$publication = Services::get('publication')->add($publication, $request);
		$publicationProps = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		return $response->withJson($publicationProps, 200);
	}

	/**
	 * Create a new version of a publication
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function versionPublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publication = Services::get('publication')->get((int) $args['publicationId']);

		if (!$publication) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if ($submission->getId() !== $publication->getData('submissionId')) {
			return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
		}

		$publication = Services::get('publication')->version($publication, $request);

		$publicationProps = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		return $response->withJson($publicationProps, 200);
	}

	/**
	 * Edit one of this submission's publications
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function editPublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publication = Services::get('publication')->get((int) $args['publicationId']);

		if (!$publication) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if ($submission->getId() !== $publication->getData('submissionId')) {
			return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
		}

		// Publications can not be edited when they are published
		if ($publication->getData('status') === STATUS_PUBLISHED) {
			return $response->withStatus(403)->withJsonError('api.publication.403.cantEditPublished');
		}

		$params = $this->convertStringsToSchema(SCHEMA_PUBLICATION, $slimRequest->getParsedBody());
		$params['id'] = $publication->getId();

		// Don't allow the status to be modified through the API. The `/publish` and /unpublish endpoints
		// should be used instead.
		if (array_key_exists('status', $params)) {
			return $response->withStatus(403)->withJsonError('api.publication.403.cantEditStatus');
		}

		$submissionContext = $request->getContext();
		if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
			$submissionContext = Services::get('context')->get($submission->getData('contextId'));
		}
		$primaryLocale = $submissionContext->getPrimaryLocale();
		$allowedLocales = $submissionContext->getSupportedLocales();

		$errors = Services::get('publication')->validate(VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$publication = Services::get('publication')->edit($publication, $params, $request);

		$publicationProps = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		return $response->withJson($publicationProps, 200);
	}

	/**
	 * Publish one of this submission's publications
	 *
	 * If this is a GET request, it will run the pre-publish validation
	 * checks and return errors but it will not perform the final
	 * publication step.
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function publishPublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publication = Services::get('publication')->get((int) $args['publicationId']);

		if (!$publication) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if ($submission->getId() !== $publication->getData('submissionId')) {
			return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
		}

		if ($publication->getData('status') === STATUS_PUBLISHED) {
			return $response->withStatus(403)->withJsonError('api.publication.403.alreadyPublished');
		}

		AppLocale::requireComponents(LOCALE_COMPONENT_PKP_SUBMISSION, LOCALE_COMPONENT_APP_SUBMISSION);

		$submissionContext = $request->getContext();
		if (!$submissionContext || $submissionContext->getId() !== $submission->getData('contextId')) {
			$submissionContext = Services::get('context')->get($submission->getData('contextId'));
		}
		$primaryLocale = $submissionContext->getPrimaryLocale();
		$allowedLocales = $submissionContext->getSupportedLocales();

		$errors = Services::get('publication')->validatePublish($publication, $submission, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		// Don't publish the publication for GET requests
		if ($slimRequest->isGet()) {
			return $response->withJson([], 200);
		}

		$publication = Services::get('publication')->publish($publication);

		$publicationProps = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		return $response->withJson($publicationProps, 200);
	}

	/**
	 * Unpublish one of this submission's publications
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function unpublishPublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publication = Services::get('publication')->get((int) $args['publicationId']);

		if (!$publication) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if ($submission->getId() !== $publication->getData('submissionId')) {
			return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
		}

		if (!in_array($publication->getData('status'), [STATUS_PUBLISHED, STATUS_SCHEDULED])) {
			return $response->withStatus(403)->withJsonError('api.publication.403.alreadyUnpublished');
		}

		$publication = Services::get('publication')->unpublish($publication);

		$publicationProps = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		return $response->withJson($publicationProps, 200);
	}

	/**
	 * Delete one of this submission's publications
	 *
	 * Published publications can not be deleted. First you must unpublish them.
	 * See self::unpublishPublication().
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function deletePublication($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publication = Services::get('publication')->get((int) $args['publicationId']);

		if (!$publication) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		if ($submission->getId() !== $publication->getData('submissionId')) {
			return $response->withStatus(403)->withJsonError('api.publications.403.submissionsDidNotMatch');
		}

		if ($publication->getData('status') === STATUS_PUBLISHED) {
			return $response->withStatus(403)->withJsonError('api.publication.403.cantDeletePublished');
		}

		$publicationProps = Services::get('publication')->getFullProperties(
			$publication,
			[
				'request' => $request,
				'userGroups' => DAORegistry::getDAO('UserGroupDAO')->getByContextId($submission->getData('contextId'))->toArray(),
			]
		);

		Services::get('publication')->delete($publication);

		return $response->withJson($publicationProps, 200);
	}
}
