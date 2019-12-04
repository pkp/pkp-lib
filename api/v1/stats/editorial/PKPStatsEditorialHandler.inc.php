<?php

/**
 * @file api/v1/stats/PKPStatsEditorialHandler.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsEditorialHandler
 * @ingroup api_v1_stats
 *
 * @brief Handle API requests for publication statistics.
 *
 */

import('lib.pkp.classes.handler.APIHandler');
import('classes.core.Services');

abstract class PKPStatsEditorialHandler extends APIHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->_handlerPath = 'stats/editorial';
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'get'],
					'roles' => [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER],
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
	 * Get editorial stats
	 *
	 * Returns information on submissions received, accepted, declined,
	 * average response times and more.
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

		$params = [];
		foreach ($slimRequest->getQueryParams() as $param => $value) {
			switch ($param) {
				case 'dateStart':
				case 'dateEnd':
					$params[$param] = $value;
					break;

				case $this->sectionIdsQueryParam:
					if (is_string($value) && strpos($value, ',') > -1) {
						$value = explode(',', $value);
					} elseif (!is_array($value)) {
						$value = [$value];
					}
					$params[$param] = array_map('intval', $value);
					break;
			}
		}

		\HookRegistry::call('API::stats::editorial::params', array(&$params, $slimRequest));

		$params['contextIds'] = [$request->getContext()->getId()];

		$result = $this->_validateStatDates($params);
		if ($result !== true) {
			return $response->withStatus(400)->withJsonError($result);
		}

		return $response->withJson(Services::get('editorialStats')->getOverview($params));
	}
}
