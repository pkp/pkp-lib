<?php
/**
 * @file api/v1/contexts/PKPEmailTemplateHandler.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPEmailTemplateHandler
 * @ingroup api_v1_email_templates
 *
 * @brief Base class to handle API requests for contexts (journals/presses).
 */

import('lib.pkp.classes.handler.APIHandler');

class PKPEmailTemplateHandler extends APIHandler {
	/**
	 * @copydoc APIHandler::__construct()
	 */
	public function __construct() {
		$this->_handlerPath = 'emailTemplates';
		$roles = [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER];
		$this->_endpoints = [
			'GET' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'getMany'],
					'roles' => $roles,
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{key}',
					'handler' => [$this, 'get'],
					'roles' => $roles,
				],
			],
			'POST' => [
				[
					'pattern' => $this->getEndpointPattern(),
					'handler' => [$this, 'add'],
					'roles' => $roles,
				],
			],
			'PUT' => [
				[
					'pattern' => $this->getEndpointPattern() . '/{key}',
					'handler' => [$this, 'edit'],
					'roles' => $roles,
				],
			],
			'DELETE' => [
				[
					'pattern' => $this->getEndpointPattern() . '/restoreDefaults',
					'handler' => [$this, 'restoreDefaults'],
					'roles' => $roles,
				],
				[
					'pattern' => $this->getEndpointPattern() . '/{key}',
					'handler' => [$this, 'delete'],
					'roles' => $roles,
				],
			],
		];
		parent::__construct();
	}

	/**
	 * @copydoc PKPHandler::authorize
	 */
	public function authorize($request, &$args, $roleAssignments) {
		import('lib.pkp.classes.security.authorization.PolicySet');
		$rolePolicy = new PolicySet(COMBINING_PERMIT_OVERRIDES);

		// This endpoint is not available at the site-wide level
		import('lib.pkp.classes.security.authorization.ContextRequiredPolicy');
		$this->addPolicy(new ContextRequiredPolicy($request));

		import('lib.pkp.classes.security.authorization.RoleBasedHandlerOperationPolicy');
		foreach ($roleAssignments as $role => $operations) {
			$rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
		}
		$this->addPolicy($rolePolicy);

		return parent::authorize($request, $args, $roleAssignments);
	}

	/**
	 * Get a collection of email templates
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$allowedParams = [];

		// Process query params to format incoming data as needed
		foreach ($slimRequest->getQueryParams() as $param => $val) {
			switch ($param) {
				case 'isCustom':
				case 'isEnabled':
					$allowedParams[$param] = (bool) $val;
					break;

				case 'fromRoleIds':
				case 'toRoleIds':
					if (is_string($val) && strpos($val, ',') > -1) {
						$val = explode(',', $val);
					} elseif (!is_array($val)) {
						$val = array($val);
					}
					$allowedParams[$param] = array_map('intval', $val);
					break;
				case 'searchPhrase':
					$allowedParams[$param] = trim($val);
					break;
			}
		}

		\HookRegistry::call('API::emailTemplates::params', array(&$allowedParams, $slimRequest));

		// Always restrict results to the current context
		$allowedParams['contextId'] = $request->getContext()->getId();

		$items = array();
		$emailTemplatesIterator = Services::get('emailTemplate')->getMany($allowedParams);
		foreach ($emailTemplatesIterator as $emailTemplate) {
			$items[] = Services::get('emailTemplate')->getSummaryProperties($emailTemplate, [
				'slimRequest' => $slimRequest,
				'request' => $request,
				'supportedLocales' => $request->getContext()->getData('supportedFormLocales'),
			]);
		}

		$data = array(
			'itemsMax' => Services::get('emailTemplate')->getMax($allowedParams),
			'items' => $items,
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Get a single email template
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function get($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$emailTemplate = Services::get('emailTemplate')->getByKey($request->getContext()->getId(), $args['key']);

		if (!$emailTemplate) {
			return $response->withStatus(404)->withJsonError('api.emailTemplates.404.templateNotFound');
		}

		$data = Services::get('emailTemplate')->getFullProperties($emailTemplate, [
			'slimRequest' => $slimRequest,
			'request' => $request,
			'supportedLocales' => $request->getContext()->getData('supportedFormLocales'),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Add an email template
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function add($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$requestContext = $request->getContext();

		$params = $this->convertStringsToSchema(SCHEMA_EMAIL_TEMPLATE, $slimRequest->getParsedBody());

		if (!isset($params['contexId'])) {
			$params['contextId'] = $requestContext->getId();
		}

		$primaryLocale = $requestContext->getData('primaryLocale');
		$allowedLocales = $requestContext->getData('supportedFormLocales');
		$errors = Services::get('emailTemplate')->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$emailTemplate = Application::getContextDAO()->newDataObject();
		$emailTemplate->_data = $params;
		$emailTemplate = Services::get('emailTemplate')->add($emailTemplate, $request);

		$data = Services::get('emailTemplate')->getFullProperties($emailTemplate, [
			'slimRequest' => $slimRequest,
			'request' => $request,
			'supportedLocales' => $requestContext->getData('supportedFormLocales'),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Edit an email template
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function edit($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$requestContext = $request->getContext();

		$emailTemplate = Services::get('emailTemplate')->getByKey($requestContext->getId(), $args['key']);

		if (!$emailTemplate) {
			return $response->withStatus(404)->withJsonError('api.emailTemplates.404.templateNotFound');
		}

		$params = $this->convertStringsToSchema(SCHEMA_EMAIL_TEMPLATE, $slimRequest->getParsedBody());
		$params['key'] = $args['key'];

		// Only allow admins to change the context an email template is attached to.
		// Set the contextId if it has not been npassed or the user is not an admin
		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (isset($params['contextId'])
				&& !in_array(ROLE_ID_SITE_ADMIN, $userRoles)
				&& $params['contextId'] !== $requestContext->getId()) {
			return $response->withStatus(403)->withJsonError('api.emailTemplates.403.notAllowedChangeContext');
		} elseif (!isset($params['contextId'])) {
			$params['contextId'] = $requestContext->getId();
		}

		$errors = Services::get('emailTemplate')->validate(
			VALIDATE_ACTION_EDIT,
			$params,
			$requestContext->getData('supportedFormLocales'),
			$requestContext->getData('primaryLocale')
		);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$emailTemplate = Services::get('emailTemplate')->edit($emailTemplate, $params, $request);

		$data = Services::get('emailTemplate')->getFullProperties($emailTemplate, [
			'slimRequest' => $slimRequest,
			'request' => $request,
			'supportedLocales' => $requestContext->getData('supportedFormLocales'),
		]);

		return $response->withJson($data, 200);
	}

	/**
	 * Delete an email template
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function delete($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$requestContext = $request->getContext();

		$emailTemplate = Services::get('emailTemplate')->getByKey($requestContext->getId(), $args['key']);

		// Only custom email templates can be deleted, so return 404 if no id exists
		if (!$emailTemplate || !$emailTemplate->getData('id')) {
			return $response->withStatus(404)->withJsonError('api.emailTemplates.404.templateNotFound');
		}

		$emailTemplateProps = Services::get('emailTemplate')->getFullProperties($emailTemplate, [
			'slimRequest' => $slimRequest,
			'request' => $request,
			'supportedLocales' => $requestContext->getData('supportedFormLocales'),
		]);

		Services::get('emailTemplate')->delete($emailTemplate);

		return $response->withJson($emailTemplateProps, 200);
	}

	/**
	 * Restore defaults in the email template settings
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function restoreDefaults($slimRequest, $response, $args) {
		$contextId = $this->getRequest()->getContext()->getId();
		$deletedKeys = Services::get('emailTemplate')->restoreDefaults($contextId);
		return $response->withJson($deletedKeys, 200);
	}
}
