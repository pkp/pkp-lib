<?php
/**
 * @file api/v1/site/PKPSiteHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteHandler
 *
 * @ingroup api_v1_users
 *
 * @brief Base class to handle API requests for the site object.
 */

namespace PKP\API\v1\site;

use APP\core\Application;
use APP\core\Services;
use APP\template\TemplateManager;
use PKP\core\APIResponse;
use PKP\handler\APIHandler;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use Slim\Http\Request as SlimRequest;

class PKPSiteHandler extends APIHandler
{
    /** @var string One of the SCHEMA_... constants */
    public $schemaName = PKPSchemaService::SCHEMA_SITE;

    /**
     * @copydoc APIHandler::__construct()
     */
    public function __construct()
    {
        $this->_handlerPath = 'site';
        $roles = [Role::ROLE_ID_SITE_ADMIN];
        $this->_endpoints = [
            'GET' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'get'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/theme',
                    'handler' => [$this, 'getTheme'],
                    'roles' => $roles,
                ],
            ],
            'PUT' => [
                [
                    'pattern' => $this->getEndpointPattern(),
                    'handler' => [$this, 'edit'],
                    'roles' => $roles,
                ],
                [
                    'pattern' => $this->getEndpointPattern() . '/theme',
                    'handler' => [$this, 'editTheme'],
                    'roles' => $roles,
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
     * Get the site
     *
     * @param SlimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function get($slimRequest, $response, $args)
    {
        $request = $this->getRequest();

        $siteProps = Services::get('site')
            ->getFullProperties($request->getSite(), [
                'request' => $request,
            ]);

        return $response->withJson($siteProps, 200);
    }

    /**
     * Get the active theme on the site
     *
     * @param SlimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function getTheme($slimRequest, $response, $args)
    {
        $site = $this->getRequest()->getSite();
        /** @var ThemePlugin[] */
        $allThemes = PluginRegistry::loadCategory('themes', true);
        /** @var ?ThemePlugin */
        $activeTheme = null;
        foreach ($allThemes as $theme) {
            if ($site->getData('themePluginPath') === $theme->getDirName()) {
                $activeTheme = $theme;
                break;
            }
        }

        if (!$activeTheme) {
            return $response->withStatus(404)->withJsonError('api.themes.404.themeUnavailable');
        }

        $data = array_merge(
            $activeTheme->getOptionValues(\PKP\core\PKPApplication::CONTEXT_ID_NONE),
            ['themePluginPath' => $theme->getDirName()]
        );

        ksort($data);

        return $response->withJson($data, 200);
    }

    /**
     * Edit the site
     *
     * @param SlimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function edit($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $site = $request->getSite();
        $siteService = Services::get('site');

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SITE, $slimRequest->getParsedBody());

        $errors = $siteService->validate($params, $site->getSupportedLocales(), $site->getPrimaryLocale());

        if (!empty($errors)) {
            return $response->withStatus(400)->withJson($errors);
        }
        $site = $siteService->edit($site, $params, $request);

        $siteProps = $siteService->getFullProperties($site, [
            'request' => $request,
            'slimRequest' => $slimRequest
        ]);

        return $response->withJson($siteProps, 200);
    }

    /**
     * Edit the active theme and theme options on the site
     *
     * @param SlimRequest $slimRequest Slim request object
     * @param APIResponse $response object
     * @param array $args arguments
     *
     * @return APIResponse
     */
    public function editTheme($slimRequest, $response, $args)
    {
        $request = $this->getRequest();
        $site = $request->getSite();
        $siteService = Services::get('site');

        $params = $slimRequest->getParsedBody();

        // Validate the themePluginPath and allow themes to perform their own validation
        $themePluginPath = empty($params['themePluginPath']) ? null : $params['themePluginPath'];
        if ($themePluginPath !== $site->getData('themePluginPath')) {
            $errors = $siteService->validate(
                ['themePluginPath' => $themePluginPath],
                $site->getSupportedLocales(),
                $site->getPrimaryLocale()
            );
            if (!empty($errors)) {
                return $response->withJson($errors, 400);
            }
            $newSite = $siteService->edit($site, ['themePluginPath' => $themePluginPath], $request);
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
        if (isset($newSite)) {
            $selectedTheme->init();
        }

        $errors = $selectedTheme->validateOptions($params, $themePluginPath, \PKP\core\PKPApplication::CONTEXT_ID_NONE, $request);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        // Only accept params that are defined in the theme options
        $options = $selectedTheme->getOptionsConfig();
        foreach ($options as $optionName => $optionConfig) {
            if (!array_key_exists($optionName, $params)) {
                continue;
            }
            $selectedTheme->saveOption($optionName, $params[$optionName], \PKP\core\PKPApplication::CONTEXT_ID_NONE);
        }

        // Clear the template cache so that new settings can take effect
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->clearTemplateCache();
        $templateMgr->clearCssCache();

        $data = array_merge(
            $selectedTheme->getOptionValues(\PKP\core\PKPApplication::CONTEXT_ID_NONE),
            ['themePluginPath' => $themePluginPath]
        );

        ksort($data);

        return $response->withJson($data, 200);
    }
}
