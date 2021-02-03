<?php
/**
 * @file api/v1/contexts/PKPContextHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextHandler
 * @ingroup api_v1_context
 *
 * @brief Base class to handle API requests for contexts (journals/presses).
 */

import('lib.pkp.classes.handler.APIHandler');

class PKPContextHandler extends APIHandler {
	/** @var string One of the SCHEMA_... constants */
	public $schemaName = SCHEMA_CONTEXT;

	/**
	 * @copydoc APIHandler::__construct()
	 */
	public function __construct() {
		$this->_handlerPath = 'contexts';
		$roles = array(ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER);
		$this->_endpoints = array(
			'GET' => array(
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'getMany'),
					'roles' => $roles,
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{contextId}',
					'handler' => array($this, 'get'),
					'roles' => $roles,
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{contextId}/theme',
					'handler' => array($this, 'getTheme'),
					'roles' => $roles,
				),
			),
			'POST' => array(
				array(
					'pattern' => $this->getEndpointPattern(),
					'handler' => array($this, 'add'),
					'roles' => array(ROLE_ID_SITE_ADMIN),
				),
			),
			'PUT' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{contextId}',
					'handler' => array($this, 'edit'),
					'roles' => $roles,
				),
				array(
					'pattern' => $this->getEndpointPattern() . '/{contextId}/theme',
					'handler' => array($this, 'editTheme'),
					'roles' => $roles,
				),
			),
			'DELETE' => array(
				array(
					'pattern' => $this->getEndpointPattern() . '/{contextId}',
					'handler' => array($this, 'delete'),
					'roles' => array(ROLE_ID_SITE_ADMIN),
				),
			),
		);
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
	 * Get a collection of contexts
	 *
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param $args array arguments
	 * @return Response
	 */
	public function getMany($slimRequest, $response, $args) {
		$request = $this->getRequest();

		$defaultParams = array(
			'count' => 20,
			'offset' => 0,
		);

		$requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

		$allowedParams = array();

		// Process query params to format incoming data as needed
		foreach ($requestParams as $param => $val) {
			switch ($param) {
				case 'isEnabled':
					$allowedParams[$param] = (bool) $val;
					break;

				case 'searchPhrase':
					$allowedParams[$param] = trim($val);
					break;

				case 'count':
					$allowedParams[$param] = min(100, (int) $val);
					break;

				case 'offset':
					$allowedParams[$param] = (int) $val;
					break;
			}
		}

		\HookRegistry::call('API::contexts::params', array(&$allowedParams, $slimRequest));

		// Anyone not a site admin should not be able to access contexts that are
		// not enabled
		if (empty($allowedParams['isEnabled'])) {
			$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
			$canAccessDisabledContexts = !empty(array_intersect(array(ROLE_ID_SITE_ADMIN), $userRoles));
			if (!$canAccessDisabledContexts) {
				return $response->withStatus(403)->withJsonError('api.contexts.403.requestedDisabledContexts');
			}
		}

		$items = array();
		$contextsIterator = Services::get('context')->getMany($allowedParams);
		$propertyArgs = array(
			'request' => $request,
			'slimRequest' => $slimRequest,
		);
		foreach ($contextsIterator as $context) {
			$items[] = Services::get('context')->getSummaryProperties($context, $propertyArgs);
		}

		$data = array(
			'itemsMax' => Services::get('context')->getMax($allowedParams),
			'items' => $items,
		);

		return $response->withJson($data, 200);
	}

	/**
	 * Get a single context
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function get($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$user = $request->getUser();

		$contextService = Services::get('context');
		$context = $contextService->get((int) $args['contextId']);

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
		}

		// Don't allow to get one context from a different context's endpoint
		if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
		}

		// A disabled journal can only be access by site admins and users with a
		// manager role in that journal
		if (!$context->getEnabled()) {
			$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
			if (!in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
				$roleDao = DaoRegistry::getDao('RoleDAO');
				if (!$roleDao->userHasRole($context->getId(), $user->getId(), ROLE_ID_MANAGER)) {
					return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowed');
				}
			}
		}

		$data = $contextService->getFullProperties($context, array(
			'request' => $request,
			'slimRequest' => $slimRequest
		));

		return $response->withJson($data, 200);
	}

	/**
	 * Get the theme and any theme options for a context
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function getTheme($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$user = $request->getUser();

		$contextService = Services::get('context');
		$context = $contextService->get((int) $args['contextId']);

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
		}

		// Don't allow to get one context from a different context's endpoint
		if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
		}

		// A disabled journal can only be access by site admins and users with a
		// manager role in that journal
		if (!$context->getEnabled()) {
			$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
			if (!in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
				$roleDao = DaoRegistry::getDao('RoleDAO');
				if (!$roleDao->userHasRole($context->getId(), $user->getId(), ROLE_ID_MANAGER)) {
					return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowed');
				}
			}
		}

		$allThemes = PluginRegistry::loadCategory('themes', true);
		$activeTheme = null;
		foreach ($allThemes as $theme) {
			if ($context->getData('themePluginPath') === $theme->getDirName()) {
				$activeTheme = $theme;
				break;
			}
		}

		if (!$activeTheme) {
			return $response->withStatus(404)->withJsonError('api.themes.404.themeUnavailable');
		}

		$data = array_merge(
			$activeTheme->getOptionValues($context->getId()),
			['themePluginPath' => $theme->getDirName()]
		);

		ksort($data);

		return $response->withJson($data, 200);
	}

	/**
	 * Add a context
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function add($slimRequest, $response, $args) {
		$request = $this->getRequest();

		// This endpoint is only available at the site-wide level
		if ($request->getContext()) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.siteWideEndpoint');
		}

		$site = $request->getSite();
		$params = $this->convertStringsToSchema(SCHEMA_CONTEXT, $slimRequest->getParsedBody());

		$primaryLocale = $site->getPrimaryLocale();
		$allowedLocales = $site->getSupportedLocales();
		$contextService = Services::get('context');
		$errors = $contextService->validate(VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}

		$context = Application::getContextDAO()->newDataObject();
		$context->setAllData($params);
		$context = $contextService->add($context, $request);
		$contextProps = $contextService->getFullProperties($context, array(
			'request' => $request,
			'slimRequest' 	=> $slimRequest
		));

		return $response->withJson($contextProps, 200);
	}

	/**
	 * Edit a context
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function edit($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$requestContext = $request->getContext();

		$contextId = (int) $args['contextId'];

		// Don't allow to get one context from a different context's endpoint
		if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
		}

		// Don't allow to edit the context from the site-wide API, because the
		// context's plugins will not be enabled
		if (!$request->getContext()) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.requiresContext');
		}

		$contextService = Services::get('context');
		$context = $contextService->get($contextId);

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
		}

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (!$requestContext && !in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
		}

		$params = $this->convertStringsToSchema(SCHEMA_CONTEXT, $slimRequest->getParsedBody());
		$params['id'] = $contextId;

		$site = $request->getSite();
		$primaryLocale = $context->getPrimaryLocale();
		$allowedLocales = $context->getSupportedFormLocales();

		$errors = $contextService->validate(VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

		if (!empty($errors)) {
			return $response->withStatus(400)->withJson($errors);
		}
		$context = $contextService->edit($context, $params, $request);

		$contextProps = $contextService->getFullProperties($context, array(
			'request' => $request,
			'slimRequest' 	=> $slimRequest
		));

		return $response->withJson($contextProps, 200);
	}

	/**
	 * Edit a context's theme and theme options
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function editTheme($slimRequest, $response, $args) {
		$request = $this->getRequest();
		$requestContext = $request->getContext();

		$contextId = (int) $args['contextId'];

		// Don't allow to get one context from a different context's endpoint
		if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
		}

		// Don't allow to edit the context from the site-wide API, because the
		// context's plugins will not be enabled
		if (!$request->getContext()) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.requiresContext');
		}

		$contextService = Services::get('context');
		$context = $contextService->get($contextId);

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
		}

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (!$requestContext && !in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
			return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
		}

		$params = $slimRequest->getParsedBody();

		// Validate the themePluginPath and allow themes to perform their own validation
		$themePluginPath = empty($params['themePluginPath']) ? null : $params['themePluginPath'];
		if ($themePluginPath !== $context->getData('themePluginPath')) {
			$errors = $contextService->validate(
				VALIDATE_ACTION_EDIT,
				['themePluginPath' => $themePluginPath],
				$context->getSupportedFormLocales(),
				$context->getPrimaryLocale()
			);
			if (!empty($errors)) {
				return $response->withJson($errors, 400);
			}
			$newContext = $contextService->edit($context, ['themePluginPath' => $themePluginPath], $request);
		}

		// Get the appropriate theme plugin
		$allThemes = PluginRegistry::loadCategory('themes', true);
		$selectedTheme = null;
		foreach ($allThemes as $theme) {
			if ($themePluginPath === $theme->getDirName()) {
				$selectedTheme = $theme;
				break;
			}
		}

		// Run the theme's init() method if a new theme has been selected
		if (isset($newContext)) {
			$selectedTheme->init();
		}

		$errors = $selectedTheme->validateOptions($params, $themePluginPath, $context->getId(), $request);
		if (!empty($errors)) {
			return $response->withJson($errors, 400);
		}

		// Only accept params that are defined in the theme options
		$options = $selectedTheme->getOptionsConfig();
		foreach ($options as $optionName => $optionConfig) {
			if (!array_key_exists($optionName, $params)) {
				continue;
			}
			$selectedTheme->saveOption($optionName, $params[$optionName], $context->getId());
		}

		// Clear the template cache so that new settings can take effect
		$templateMgr = TemplateManager::getManager(Application::get()->getRequest());
		$templateMgr->clearTemplateCache();
		$templateMgr->clearCssCache();

		$data = array_merge(
			$selectedTheme->getOptionValues($context->getId()),
			['themePluginPath' => $themePluginPath]
		);

		ksort($data);

		return $response->withJson($data, 200);
	}

	/**
	 * Delete a context
	 * @param $slimRequest Request Slim request object
	 * @param $response Response object
	 * @param array $args arguments
	 *
	 * @return Response
	 */
	public function delete($slimRequest, $response, $args) {

		// This endpoint is only available at the site-wide level
		if ($this->getRequest()->getContext()) {
			return $response->withStatus(404)->withJsonError('api.submissions.404.siteWideEndpoint');
		}

		$userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
		if (!in_array(ROLE_ID_SITE_ADMIN, $userRoles)) {
			$response->withStatus(403)->withJsonError('api.contexts.403.notAllowedDelete');
		}

		$contextId = (int) $args['contextId'];

		$contextService = Services::get('context');
		$context = $contextService->get($contextId);

		if (!$context) {
			return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
		}

		$contextProps = $contextService->getSummaryProperties($context, array(
			'request' => $this->getRequest(),
			'slimRequest' 	=> $slimRequest
		));

		$contextService->delete($context);

		return $response->withJson($contextProps, 200);
	}
}
