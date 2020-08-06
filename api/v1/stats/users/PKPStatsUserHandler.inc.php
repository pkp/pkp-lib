<?php

/**
 * @file api/v1/stats/PKPStatsUserHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2003-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsUserHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for publication statistics.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

class PKPStatsUserHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'stats/users';
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'get'],
					'roles' => [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR],
				],
			],
		];
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize()
	 */
	function authorize($request, &$args, $roleAssignments) {

		import('lib.pkp.classes.security.authorization.ContextAccessPolicy');
		$this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));

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
	 * Get user stats
	 *
	 * Returns the count of users broken down by roles
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response object Response
	 * @param $args array
	 * @return object Response
	 */
	public function get($slimRequest, $response, $args) {
		$request = $this->getRequest();

		if (!$request->getContext()) {
			return $response->withStatus(404)->withJsonError('api.404.resourceNotFound');
		}

		$defaultParams = [
			'status' => 'active'
		];

		$params = [];
		foreach ($slimRequest->getQueryParams() as $param => $value) {
			switch ($param) {
				case 'registeredAfter':
				case 'registeredBefore':
					$params[$param] = $value;
					break;

				case 'status':
					$params[$param] = $value === 'disabled' ? $value : 'active';
					break;
			}
		}

		$params = array_merge($defaultParams, $params);

		\HookRegistry::call('API::stats::users::params', array(&$params, $slimRequest));

		$params['contextId'] = [$request->getContext()->getId()];

		$result = $this->_validateStatDates($params, 'registeredAfter', 'registeredBefore');
		if ($result !== true) {
			return $response->withStatus(400)->withJsonError($result);
		}

		return $response->withJson(Services::get('user')->getRolesOverview($params));
	}
}
