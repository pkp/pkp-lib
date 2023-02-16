<?php
/**
 * @file api/v1/contexts/PKPContextHandler.php
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

namespace PKP\API\v1\contexts;

use APP\core\Application;
use APP\core\Services;
use APP\plugins\IDoiRegistrationAgency;
use APP\services\ContextService;
use APP\template\TemplateManager;
use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\handler\APIHandler;
use PKP\plugins\Hook;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\security\RoleDAO;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\PKPSchemaService;
use Slim\Http\Request as SlimRequest;
use Slim\Http\Response as SlimResponse;

class PKPContextHandler extends APIHandler
{
    /** @var string One of the SCHEMA_... constants */
    public $schemaName = PKPSchemaService::SCHEMA_CONTEXT;

    /**
     * @copydoc APIHandler::__construct()
     */
    public function __construct()
    {
        $this->_handlerPath = 'contexts';
        $roles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'getMany'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}',
                    'handler' => [$this, 'get'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}/theme',
                    'handler' => [$this, 'getTheme'],
                    'roles' => $roles,
                ],
            ],
            'POST' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'add'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}',
                    'handler' => [$this, 'edit'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}/theme',
                    'handler' => [$this, 'editTheme'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}/registrationAgency',
                    'handler' => [$this, 'editDoiRegistrationAgencyPlugin'],
                    'roles' => $roles,
                ]
            ],
            'DELETE' => [
                [
                    'pattern' => $this->getEndpointPattern() . '/{contextId:\d+}',
                    'handler' => [$this, 'delete'],
                    'roles' => [Role::ROLE_ID_SITE_ADMIN],
                ],
            ],
        ];
        parent::__construct();
    }

    /**
     * @copydoc PKPHandler::authorize
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new UserRolesRequiredPolicy($request), true);

        $rolePolicy = new PolicySet(PolicySet::COMBINING_PERMIT_OVERRIDES);

        foreach ($roleAssignments as $role => $operations) {
            $rolePolicy->addPolicy(new RoleBasedHandlerOperationPolicy($request, $role, $operations));
        }
        $this->addPolicy($rolePolicy);

        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Get a collection of contexts
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getMany($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        $defaultParams = [
            'count' => 20,
            'offset' => 0,
        ];

        $requestParams = array_merge($defaultParams, $slimRequest->getQueryParams());

        $allowedParams = [];

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

        Hook::call('API::contexts::params', [&$allowedParams, $slimRequest]);

        // Anyone not a site admin should not be able to access contexts that are
        // not enabled
        if (empty($allowedParams['isEnabled'])) {
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            $canAccessDisabledContexts = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN], $userRoles));
            if (!$canAccessDisabledContexts) {
                return $response->withStatus(403)->withJsonError('api.contexts.403.requestedDisabledContexts');
            }
        }

        $items = [];
        $contextsIterator = Services::get('context')->getMany($allowedParams);
        $propertyArgs = [
            'request' => $request,
            'slimRequest' => $slimRequest,
        ];
        foreach ($contextsIterator as $context) {
            $items[] = Services::get('context')->getSummaryProperties($context, $propertyArgs);
        }

        $data = [
            'itemsMax' => Services::get('context')->getMax($allowedParams),
            'items' => $items,
        ];

        return $response->withJson($data, 200);
    }

    /**
     * Get a single context
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function get($slimRequest, $response, $args)
    {
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
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
                $roleDao = DAORegistry::getDao('RoleDAO'); /** @var RoleDAO $roleDao */
                if (!$roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_MANAGER)) {
                    return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowed');
                }
            }
        }

        $data = $contextService->getFullProperties($context, [
            'request' => $request,
            'slimRequest' => $slimRequest
        ]);

        return $response->withJson($data, 200);
    }

    /**
     * Get the theme and any theme options for a context
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function getTheme($slimRequest, $response, $args)
    {
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
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
                $roleDao = DAORegistry::getDao('RoleDAO'); /** @var RoleDAO $roleDao */
                if (!$roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_MANAGER)) {
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
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function add($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        // This endpoint is only available at the site-wide level
        if ($request->getContext()) {
            return $response->withStatus(404)->withJsonError('api.submissions.404.siteWideEndpoint');
        }

        $site = $request->getSite();
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTEXT, $slimRequest->getParsedBody());

        $primaryLocale = $site->getPrimaryLocale();
        $allowedLocales = $site->getSupportedLocales();

        // If the site only supports a single locale, set the context's locales
        if (count($allowedLocales) === 1) {
            if (!isset($params['primaryLocale'])) {
                $params['primaryLocale'] = $primaryLocale;
            }
            if (!isset($params['supportedLocales'])) {
                $params['supportedLocales'] = $allowedLocales;
            }
        }

        $contextService = Services::get('context'); /** @var ContextService $contextService */
        $errors = $contextService->validate(EntityWriteInterface::VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $context = Application::getContextDAO()->newDataObject();
        $context->setAllData($params);
        $context = $contextService->add($context, $request);
        $contextProps = $contextService->getFullProperties($context, [
            'request' => $request,
            'slimRequest' => $slimRequest
        ]);

        return $response->withJson($contextProps, 200);
    }

    /**
     * Edit a context
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function edit($slimRequest, $response, $args)
    {
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

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!$requestContext && !in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTEXT, $slimRequest->getParsedBody());
        $params['id'] = $contextId;

        $site = $request->getSite();
        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();

        $errors = $contextService->validate(EntityWriteInterface::VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }
        $context = $contextService->edit($context, $params, $request);

        $contextProps = $contextService->getFullProperties($context, [
            'request' => $request,
            'slimRequest' => $slimRequest
        ]);

        return $response->withJson($contextProps, 200);
    }

    /**
     * Edit a context's theme and theme options
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function editTheme($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $args['contextId'];

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$requestContext) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.requiresContext');
        }

        $contextService = Services::get('context');
        $context = $contextService->get($contextId);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
        }

        $params = $slimRequest->getParsedBody();

        // Validate the themePluginPath and allow themes to perform their own validation
        $themePluginPath = empty($params['themePluginPath']) ? null : $params['themePluginPath'];
        if ($themePluginPath !== $context->getData('themePluginPath')) {
            $errors = $contextService->validate(
                EntityWriteInterface::VALIDATE_ACTION_EDIT,
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

    public function editDoiRegistrationAgencyPlugin(SlimRequest $slimRequest, SlimResponse $response, array $args): SlimResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $args['contextId'];

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.contextsDidNotMatch');
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$requestContext) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.requiresContext');
        }

        /** @var ContextService $contextService */
        $contextService = Services::get('context');
        $context = $contextService->get($contextId);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles)) {
            return $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedEdit');
        }

        /** @var PKPSchemaService $schemaService */
        $schemaService = Services::get('schema');

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTEXT, $slimRequest->getParsedBody());
        $contextFullProps = array_flip($schemaService->getFullProps(PKPSchemaService::SCHEMA_CONTEXT));
        $contextParams = array_intersect_key(
            $params,
            $contextFullProps,
        );

        // Validate the registrationAgency and automatic deposit fields
        // and allow agencies to perform their own validation.
        if (!empty($contextParams)) {
            $errors = $contextService->validate(
                ContextService::VALIDATE_ACTION_EDIT,
                $contextParams,
                $context->getSupportedFormLocales(),
                $context->getPrimaryLocale(),
            );

            if (!empty($errors)) {
                return $response->withJson($errors, 400);
            }
            $contextService->edit(
                $context,
                $contextParams,
                $request
            );
        }

        // Return if no registration agency enabled;
        if ($contextParams[Context::SETTING_CONFIGURED_REGISTRATION_AGENCY] === null) {
            return $response->withJson($contextParams, 200);
        }

        // Get the appropriate agency plugin
        $plugins = PluginRegistry::loadCategory('generic', true);
        $selectedPlugin = null;
        foreach ($plugins as $plugin) {
            if (
                $contextParams[Context::SETTING_CONFIGURED_REGISTRATION_AGENCY] === $plugin->getName()
            ) {
                $selectedPlugin = $plugin;
                break;
            }
        }

        // Check if it's a registration agency plugin
        if (!$selectedPlugin instanceof IDoiRegistrationAgency) {
            return $response->withStatus(400)->withJsonError('api.dois.400.invalidPluginType');
        }

        // If it's a new/different registration agency plugin, update the enabled DOI types based on
        // allowed types per the registration agency plugin
        if (
            $context->getData(Context::SETTING_CONFIGURED_REGISTRATION_AGENCY) !== $contextParams[Context::SETTING_CONFIGURED_REGISTRATION_AGENCY] &&
            $contextParams[Context::SETTING_CONFIGURED_REGISTRATION_AGENCY] !== null
        ) {
            /** @var Context $newContext */
            $newContext = $contextService->get($contextId);
            $enabledPubObjectTypes = $newContext->getEnabledDoiTypes();
            $allowedPubObjectTypes = $selectedPlugin->getAllowedDoiTypes();
            $filteredPubObjectTypes = array_intersect($enabledPubObjectTypes, $allowedPubObjectTypes);

            if ($filteredPubObjectTypes != $enabledPubObjectTypes) {
                $contextService->edit(
                    $newContext,
                    [Context::SETTING_ENABLED_DOI_TYPES => $filteredPubObjectTypes],
                    $request
                );
            }
        }

        $settingsObject = $selectedPlugin->getSettingsObject();
        $params = $this->convertStringsToSchema($settingsObject::class, $slimRequest->getParsedBody());
        $pluginParams = array_intersect_key(
            $params,
            (array) $settingsObject->getSchema()->properties,
        );

        // Validate plugin settings
        $errors = $settingsObject->validate($pluginParams);
        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }

        $this->updateRegistrationAgencyPluginSettings(
            $contextId,
            $selectedPlugin,
            $settingsObject::class,
            $pluginParams,
        );

        return $response->withJson(
            array_merge($contextParams, $pluginParams),
            200,
        );
    }

    /**
     * Delete a context
     *
     * @param Request $slimRequest Slim request object
     * @param Response $response object
     * @param array $args arguments
     *
     * @return Response
     */
    public function delete($slimRequest, $response, $args)
    {

        // This endpoint is only available at the site-wide level
        if ($this->getRequest()->getContext()) {
            return $response->withStatus(404)->withJsonError('api.submissions.404.siteWideEndpoint');
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            $response->withStatus(403)->withJsonError('api.contexts.403.notAllowedDelete');
        }

        $contextId = (int) $args['contextId'];

        $contextService = Services::get('context');
        $context = $contextService->get($contextId);

        if (!$context) {
            return $response->withStatus(404)->withJsonError('api.contexts.404.contextNotFound');
        }

        $contextProps = $contextService->getSummaryProperties($context, [
            'request' => $this->getRequest(),
            'slimRequest' => $slimRequest
        ]);

        $contextService->delete($context);

        return $response->withJson($contextProps, 200);
    }

    /**
     * Updates a settings plugin according to a given schema. Used in lieu of a generic plugin settings management workflow.
     *
     * @param Plugin $plugin Currently configured registration agency plugin. Should also implement IDoiRegistrationAgency
     * @param string $schemaName Name of RegistrationAgencySettings child class used as schema name
     * @param array $props Plugin properties to update
     */
    protected function updateRegistrationAgencyPluginSettings(int $contextId, Plugin $plugin, string $schemaName, array $props): void
    {
        /** @var PKPSchemaService $schemaService */
        $schemaService = Services::get('schema');
        $sanitizedProps = $schemaService->sanitize($schemaName, $props);

        foreach ($sanitizedProps as $fieldName => $value) {
            $plugin->updateSetting($contextId, $fieldName, $value);
        }
    }
}
