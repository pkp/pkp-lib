<?php

/**
 * @file api/v1/announcements/PKPAnnouncementHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPAnnouncementHandler
 * @ingroup api_v1_announcement
 *
 * @brief Handle API requests for announcement operations.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPAnnouncementHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'announcements';
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'getMany'],
					'roles' => [ROLE_ID_MANAGER],
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{announcementId}',
					'handler' => [$this, 'get'],
					'roles' => [ROLE_ID_MANAGER],
				],
			],
			'POST' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'add'],
					'roles' => [ROLE_ID_MANAGER],
				],
			],
			'PUT' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{announcementId}',
					'handler' => [$this, 'edit'],
					'roles' => [ROLE_ID_MANAGER],
				],
			],
			'DELETE' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{announcementId}',
					'handler' => array($this, 'delete'),
					'roles' => array(ROLE_ID_MANAGER),
				),
			),
		];
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize
	 */
	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach ($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get a single submission
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function get($slimRequest, $response, $args) {

		$announcement = Services::get('announcement')->get((int) $args['announcementId']);

		if (!$announcement) {
			return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
		}

		// The assocId in announcements should always point to the contextId
		if ($announcement->getData('assocId') !== $this->getRequest()->getContext()->getId()) {
			return $response->withStatus(404)->withJsonError('api.announcements.400.contextsNotMatched');
		}

		$props = Services::get('announcement')->getFullProperties(
			$announcement,
			[
				'request' => $this->getRequest(),
				'announcementContext' => $this->getRequest()->getContext(),
			]
		);

		return $response->withJson($props, 200);
	}

	/**
	 * Get a collection of announcements
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = Application::get()->getRequest();

		$params = [
			'count' => 30,
			'offset' => 0,
		];

		$requestParams = $slimRequest->getQueryParams();

		// Process query params to format incoming data as needed
		foreach ($requestParams as $param => $val) {
			switch ($param) {
				case 'contextIds':
				case 'typeIds':
					if (is_string($val)) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = [$val];
					}
					$params[$param] = array_map('intval', $val);
					break;
				case 'count':
				case 'offset':
					$params[$param] = (int) $val;
					break;
				case 'searchPhrase':
					$params[$param] = $val;
			}
		}

		if ($this->getRequest()->getContext()) {
			$params['contextIds'] = [$this->getRequest()->getContext()->getId()];
		}

		\HookRegistry::call('API::submissions::params', array(&$params, $slimRequest));

		$result = Services::get('announcement')->getMany($params);
		$items = [];
		if ($result->valid()) {
			foreach ($result as $announcement) {
				$items[] = Services::get('announcement')->getSummaryProperties($announcement, [
					'request' => $this->getRequest(),
					'announcementContext' => $this->getRequest()->getContext(),
				]);
			}
		}

		return $response->withJson([
			'itemsMax' => Services::get('announcement')->getMax($params),
			'items' => $items,
		], 200);
	}

	/**
	 * Add an announcement
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function add($slimRequest, $response, $args) {
		$request = $this->getRequest();

		if (!$request->getContext()) {
			throw new Exception('You can not add an announcement without sending a request to the API endpoint of a particular context.');
		}

		$params = $this->convertStringsToSchema(SCHEMA_ANNOUNCEMENT, $slimRequest->getParsedBody());
		$params['assocType'] = Application::get()->getContextAssocType();
		$params['assocId'] = $request->getContext()->getId();

		$primaryLocale = $request->getContext()->getPrimaryLocale();
		$allowedLocales = $request->getContext()->getSupportedFormLocales();
		$errors = Services::get('announcement')->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$announcement = DAORegistry::getDao('AnnouncementDAO')->newDataObject();
		$announcement->setAllData($params);
		$announcement = Services::get('announcement')->add($announcement, $request);
		$announcementProps = Services::get('announcement')->getFullProperties($announcement, [
			'request' => $request,
			'announcementContext' => $request->getContext(),
		]);

		return $response->withJson($announcementProps, 200);
	}

	/**
	 * Edit an announcement
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function edit($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$announcement = Services::get('announcement')->get((int) $args['announcementId']);

		if (!$announcement) {
			return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
		}

		if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
			throw new Exception('Announcement has an assocType that did not match the context.');
		}

		// Don't allow to edit an announcement from one context from a different context's endpoint
		if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
			return $response->withStatus(403)->withJsonError('api.announcements.400.contextsNotMatched');
		}

		$params = $this->convertStringsToSchema(SCHEMA_ANNOUNCEMENT, $slimRequest->getParsedBody());
		$params['id'] = $announcement->getId();

		$context = $request->getContext();
		$primaryLocale = $context->getPrimaryLocale();
		$allowedLocales = $context->getSupportedFormLocales();

		$errors = Services::get('announcement')->validate(VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);
		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$announcement = Services::get('announcement')->edit($announcement, $params, $request);

		$announcementProps = Services::get('announcement')->getFullProperties($announcement, [
			'request' => $request,
			'announcementContext' => $context,
		]);

		return $response->withJson($announcementProps, 200);
	}

	/**
	 * Delete an announcement
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function delete($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$announcement = Services::get('announcement')->get((int) $args['announcementId']);

		if (!$announcement) {
			return $response->withStatus(404)->withJsonError('api.announcements.404.announcementNotFound');
		}

		if ($announcement->getData('assocType') !== Application::get()->getContextAssocType()) {
			throw new Exception('Announcement has an assocType that did not match the context.');
		}

		// Don't allow to delete an announcement from one context from a different context's endpoint
		if ($request->getContext()->getId() !== $announcement->getData('assocId')) {
			return $response->withStatus(403)->withJsonError('api.announcements.400.contextsNotMatched');
		}

		$announcementProps = Services::get('announcement')->getSummaryProperties($announcement, array(
			'request' => $request,
			'announcementContext' => $request->getContext(),
		));

		Services::get('announcement')->delete($announcement);

		return $response->withJson($announcementProps, 200);
	}
}
