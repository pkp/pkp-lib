<?php
/**
 * @file api/v1/contexts/PKPContextController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPContextController
 *
 * @ingroup api_v1_context
 *
 * @brief Controller class to handle API requests for contexts (journals/presses).
 */

namespace PKP\API\v1\contexts;

use APP\core\Application;
use APP\plugins\IDoiRegistrationAgency;
use APP\services\ContextService;
use APP\template\TemplateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\context\Context;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\Plugin;
use PKP\plugins\PluginRegistry;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\interfaces\EntityWriteInterface;
use PKP\services\PKPContextService;
use PKP\services\PKPSchemaService;

class PKPContextController extends PKPBaseController
{
    /** @var string One of the SCHEMA_... constants */
    public $schemaName = PKPSchemaService::SCHEMA_CONTEXT;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'contexts';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::middleware([
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
                Role::ROLE_ID_MANAGER,
            ]),
        ])->group(function () {

            Route::get('', $this->getMany(...))
                ->name('context.getMany');

            Route::get('{contextId}', $this->get(...))
                ->name('context.getContext')
                ->whereNumber('contextId');

            Route::get('{contextId}/theme', $this->getTheme(...))
                ->name('context.getContext')
                ->whereNumber('contextId');

            Route::put('{contextId}', $this->edit(...))
                ->name('context.edit')
                ->whereNumber('contextId');

            Route::put('{contextId}/theme', $this->editTheme(...))
                ->name('context.editTheme')
                ->whereNumber('contextId');

            Route::put('{contextId}/registrationAgency', $this->editDoiRegistrationAgencyPlugin(...))
                ->name('context.edit.doiRegistration')
                ->whereNumber('contextId');
        });

        Route::middleware([
            self::roleAuthorizer([Role::ROLE_ID_SITE_ADMIN,]),
        ])->group(function () {

            Route::post('', $this->add(...))
                ->name('context.add');

            Route::delete('{contextId}', $this->delete(...))
                ->name('context.delete')
                ->whereNumber('contextId');
        });
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::authorize()
     */
    public function authorize(PKPRequest $request, array &$args, array $roleAssignments): bool
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
     * @hook API::contexts::params [[&$allowedParams, $illuminateRequest]]
     */
    public function getMany(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $defaultParams = [
            'count' => 20,
            'offset' => 0,
        ];

        $requestParams = array_merge($defaultParams, $illuminateRequest->query());

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

        Hook::call('API::contexts::params', [&$allowedParams, $illuminateRequest]);

        // Anyone not a site admin should not be able to access contexts that are
        // not enabled
        if (empty($allowedParams['isEnabled'])) {
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            $canAccessDisabledContexts = !empty(array_intersect([Role::ROLE_ID_SITE_ADMIN], $userRoles));
            if (!$canAccessDisabledContexts) {
                return response()->json([
                    'error' => __('api.contexts.403.requestedDisabledContexts'),
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $items = [];
        $contextsIterator = $contextService->getMany($allowedParams);
        $propertyArgs = [
            'request' => $request,
            'apiRequest' => $illuminateRequest,
        ];
        foreach ($contextsIterator as $context) {
            $items[] = $contextService->getSummaryProperties($context, $propertyArgs);
        }

        $data = [
            'itemsMax' => $contextService->getMax($allowedParams),
            'items' => $items,
        ];

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get a single context
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $user = $request->getUser();

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $context = $contextService->get((int) $illuminateRequest->route('contextId'));

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // A disabled journal can only be access by site admins and users with a
        // manager role in that journal
        if (!$context->getEnabled()) {
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
                $roleDao = DAORegistry::getDao('RoleDAO'); /** @var RoleDAO $roleDao */
                if (!$roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_MANAGER)) {
                    return response()->json([
                        'error' => __('api.contexts.403.notAllowed'),
                    ], Response::HTTP_FORBIDDEN);
                }
            }
        }

        $data = $contextService->getFullProperties($context, [
            'request' => $request,
            'apiRequest' => $illuminateRequest
        ]);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Get the theme and any theme options for a context
     */
    public function getTheme(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $user = $request->getUser();

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $context = $contextService->get((int) $illuminateRequest->route('contextId'));

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $context->getId()) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // A disabled journal can only be access by site admins and users with a
        // manager role in that journal
        if (!$context->getEnabled()) {
            $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
            if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
                $roleDao = DAORegistry::getDao('RoleDAO'); /** @var RoleDAO $roleDao */
                if (!$roleDao->userHasRole($context->getId(), $user->getId(), Role::ROLE_ID_MANAGER)) {
                    return response()->json([
                        'error' => __('api.contexts.403.notAllowed'),
                    ], Response::HTTP_FORBIDDEN);
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
            return response()->json([
                'error' => __('api.themes.404.themeUnavailable'),
            ], Response::HTTP_NOT_FOUND);
        }

        $data = array_merge(
            $activeTheme->getOptionValues($context->getId()),
            ['themePluginPath' => $theme->getDirName()]
        );

        ksort($data);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Add a context
     */
    public function add(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        // This endpoint is only available at the site-wide level
        if ($request->getContext()) {
            return response()->json([
                'error' => __('api.submissions.404.siteWideEndpoint'),
            ], Response::HTTP_NOT_FOUND);
        }

        $site = $request->getSite();
        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTEXT, $illuminateRequest->input());

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

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $errors = $contextService->validate(EntityWriteInterface::VALIDATE_ACTION_ADD, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $context = Application::getContextDAO()->newDataObject();
        $context->setAllData($params);
        $context = $contextService->add($context, $request);
        $contextProps = $contextService->getFullProperties($context, [
            'request' => $request,
            'apiRequest' => $illuminateRequest
        ]);

        return response()->json($contextProps, Response::HTTP_OK);
    }

    /**
     * Edit a context
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $illuminateRequest->route('contextId');

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$request->getContext()) {
            return response()->json([
                'error' => __('api.contexts.403.requiresContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $context = $contextService->get($contextId);

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!$requestContext && !in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            return response()->json([
                'error' => __('api.contexts.403.notAllowedEdit'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTEXT, $illuminateRequest->input());
        $params['id'] = $contextId;

        $site = $request->getSite();
        $primaryLocale = $context->getPrimaryLocale();
        $allowedLocales = $context->getSupportedFormLocales();

        $errors = $contextService->validate(EntityWriteInterface::VALIDATE_ACTION_EDIT, $params, $allowedLocales, $primaryLocale);

        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }
        $context = $contextService->edit($context, $params, $request);

        $contextProps = $contextService->getFullProperties($context, [
            'request' => $request,
            'apiRequest' => $illuminateRequest
        ]);

        return response()->json($contextProps, Response::HTTP_OK);
    }

    /**
     * Edit a context's theme and theme options
     */
    public function editTheme(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $illuminateRequest->route('contextId');

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$requestContext) {
            return response()->json([
                'error' => __('api.contexts.403.requiresContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $context = $contextService->get($contextId);

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $allowedRoles = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER];

        if (!array_intersect($allowedRoles, $userRoles)) {
            return response()->json([
                'error' => __('api.contexts.403.notAllowedEdit'),
            ], Response::HTTP_FORBIDDEN);
        }

        $params = $illuminateRequest->input();

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
                return response()->json($errors, Response::HTTP_BAD_REQUEST);
            }
            $newContext = $contextService->edit($context, ['themePluginPath' => $themePluginPath], $request);
        }

        // Get the appropriate theme plugin
        /** @var iterable<ThemePlugin> */
        $allThemes = PluginRegistry::loadCategory('themes', true);
        /** @var ?ThemePlugin */
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
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
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

        return response()->json($data, Response::HTTP_OK);
    }


    public function editDoiRegistrationAgencyPlugin(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $requestContext = $request->getContext();

        $contextId = (int) $illuminateRequest->route('contextId');

        // Don't allow to get one context from a different context's endpoint
        if ($request->getContext() && $request->getContext()->getId() !== $contextId) {
            return response()->json([
                'error' => __('api.contexts.403.contextsDidNotMatch'),
            ], Response::HTTP_FORBIDDEN);
        }

        // Don't allow to edit the context from the site-wide API, because the
        // context's plugins will not be enabled
        if (!$requestContext) {
            return response()->json([
                'error' => __('api.contexts.403.requiresContext'),
            ], Response::HTTP_FORBIDDEN);
        }

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $context = $contextService->get($contextId);

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles)) {
            return response()->json([
                'error' => __('api.contexts.403.notAllowedEdit'),
            ], Response::HTTP_FORBIDDEN);
        }

        $schemaService = app()->get('schema'); /** @var PKPSchemaService $schemaService */

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_CONTEXT, $illuminateRequest->input());
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
                return response()->json($errors, Response::HTTP_BAD_REQUEST);
            }
            $contextService->edit(
                $context,
                $contextParams,
                $request
            );
        }

        // Return if no registration agency enabled;
        if ($contextParams[Context::SETTING_CONFIGURED_REGISTRATION_AGENCY] === null) {
            return response()->json($contextParams, Response::HTTP_OK);
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
            return response()->json([
                'error' => __('api.dois.400.invalidPluginType'),
            ], Response::HTTP_BAD_REQUEST);
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
        $params = $this->convertStringsToSchema($settingsObject::class, $illuminateRequest->input());
        $pluginParams = array_intersect_key(
            $params,
            (array) $settingsObject->getSchema()->properties,
        );

        // Validate plugin settings
        $errors = $settingsObject->validate($pluginParams);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        $this->updateRegistrationAgencyPluginSettings(
            $contextId,
            $selectedPlugin,
            $settingsObject::class,
            $pluginParams,
        );

        return response()->json(array_merge($contextParams, $pluginParams), Response::HTTP_OK);
    }

    /**
     * Delete a context
     */
    public function delete(Request $illuminateRequest): JsonResponse
    {
        // This endpoint is only available at the site-wide level
        if ($this->getRequest()->getContext()) {
            return response()->json([
                'error' => __('api.submissions.404.siteWideEndpoint'),
            ], Response::HTTP_NOT_FOUND);
        }

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        if (!in_array(Role::ROLE_ID_SITE_ADMIN, $userRoles)) {
            return response()->json([
                'error' => __('api.contexts.403.notAllowedDelete'),
            ], Response::HTTP_FORBIDDEN);
        }

        $contextId = (int) $illuminateRequest->route('contextId');

        $contextService = app()->get('context'); /** @var PKPContextService $contextService */
        $context = $contextService->get($contextId);

        if (!$context) {
            return response()->json([
                'error' => __('api.contexts.404.contextNotFound'),
            ], Response::HTTP_NOT_FOUND);
        }

        $contextProps = $contextService->getSummaryProperties($context, [
            'request' => $this->getRequest(),
            'apiRequest' => $illuminateRequest
        ]);

        $contextService->delete($context);

        return response()->json($contextProps, Response::HTTP_OK);
    }

    /**
     * Updates a settings plugin according to a given schema. Used in lieu of a generic plugin settings management workflow.
     *
     * @param Plugin $plugin        Currently configured registration agency plugin. Should also implement IDoiRegistrationAgency
     * @param string $schemaName    Name of RegistrationAgencySettings child class used as schema name
     * @param array $props          Plugin properties to update
     *
     */
    protected function updateRegistrationAgencyPluginSettings(int $contextId, Plugin $plugin, string $schemaName, array $props): void
    {
        $schemaService = app()->get('schema'); /** @var PKPSchemaService $schemaService */
        $sanitizedProps = $schemaService->sanitize($schemaName, $props);

        foreach ($sanitizedProps as $fieldName => $value) {
            $plugin->updateSetting($contextId, $fieldName, $value);
        }
    }
}
