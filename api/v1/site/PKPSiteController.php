<?php
/**
 * @file api/v1/site/PKPSiteController.php
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSiteController
 *
 * @ingroup api_v1_users
 *
 * @brief Controller class to handle API requests for the site object.
 */

namespace PKP\API\v1\site;

use APP\core\Application;
use APP\template\TemplateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use PKP\core\PKPBaseController;
use PKP\core\PKPRequest;
use PKP\plugins\PluginRegistry;
use PKP\plugins\ThemePlugin;
use PKP\security\authorization\PolicySet;
use PKP\security\authorization\RoleBasedHandlerOperationPolicy;
use PKP\security\authorization\UserRolesRequiredPolicy;
use PKP\security\Role;
use PKP\services\PKPSchemaService;

class PKPSiteController extends PKPBaseController
{
    /** @var string One of the SCHEMA_... constants */
    public $schemaName = PKPSchemaService::SCHEMA_SITE;

    /**
     * @copydoc \PKP\core\PKPBaseController::getHandlerPath()
     */
    public function getHandlerPath(): string
    {
        return 'site';
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getRouteGroupMiddleware()
     */
    public function getRouteGroupMiddleware(): array
    {
        return [
            'has.user',
            self::roleAuthorizer([
                Role::ROLE_ID_SITE_ADMIN,
            ]),
        ];
    }

    /**
     * @copydoc \PKP\core\PKPBaseController::getGroupRoutes()
     */
    public function getGroupRoutes(): void
    {
        Route::get('', $this->get(...))
            ->name('site.getSite');

        Route::get('theme', $this->getTheme(...))
            ->name('site.getTheme');

        Route::put('', $this->edit(...))
            ->name('site.edit');

        Route::put('theme', $this->editTheme(...))
            ->name('site.editTheme');
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
     * Get the site
     */
    public function get(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();

        $siteProps = app()->get('site')
            ->getFullProperties($request->getSite(), [
                'request' => $request,
            ]);

        return response()->json($siteProps, Response::HTTP_OK);
    }

    /**
     * Get the active theme on the site
     */
    public function getTheme(Request $illuminateRequest): JsonResponse
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
            response()->json([
                'error' => __('api.themes.404.themeUnavailable'),
            ], Response::HTTP_NOT_FOUND);
        }

        $data = array_merge(
            $activeTheme->getOptionValues(Application::SITE_CONTEXT_ID),
            ['themePluginPath' => $theme->getDirName()]
        );

        ksort($data);

        return response()->json($data, Response::HTTP_OK);
    }

    /**
     * Edit the site
     */
    public function edit(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $site = $request->getSite();
        $siteService = app()->get('site');

        $params = $this->convertStringsToSchema(PKPSchemaService::SCHEMA_SITE, $illuminateRequest->input());

        $errors = $siteService->validate($params, $site->getSupportedLocales(), $site->getPrimaryLocale());

        if (!empty($errors)) {
            return response()->json($errors, REsponse::HTTP_BAD_REQUEST);
        }
        $site = $siteService->edit($site, $params, $request);

        $siteProps = $siteService->getFullProperties($site, [
            'request' => $request,
            'apiRequest' => $illuminateRequest,
        ]);

        return response()->json($siteProps, Response::HTTP_OK);
    }

    /**
     * Edit the active theme and theme options on the site
     */
    public function editTheme(Request $illuminateRequest): JsonResponse
    {
        $request = $this->getRequest();
        $site = $request->getSite();
        $siteService = app()->get('site');

        $params = $illuminateRequest->input();

        // Validate the themePluginPath and allow themes to perform their own validation
        $themePluginPath = empty($params['themePluginPath']) ? null : $params['themePluginPath'];
        if ($themePluginPath !== $site->getData('themePluginPath')) {
            $errors = $siteService->validate(
                ['themePluginPath' => $themePluginPath],
                $site->getSupportedLocales(),
                $site->getPrimaryLocale()
            );

            if (!empty($errors)) {
                return response()->json($errors, Response::HTTP_BAD_REQUEST);
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

        $errors = $selectedTheme->validateOptions($params, $themePluginPath, \PKP\core\PKPApplication::SITE_CONTEXT_ID, $request);
        if (!empty($errors)) {
            return response()->json($errors, Response::HTTP_BAD_REQUEST);
        }

        // Only accept params that are defined in the theme options
        $options = $selectedTheme->getOptionsConfig();
        foreach ($options as $optionName => $optionConfig) {
            if (!array_key_exists($optionName, $params)) {
                continue;
            }
            $selectedTheme->saveOption($optionName, $params[$optionName], \PKP\core\PKPApplication::SITE_CONTEXT_ID);
        }

        // Clear the template cache so that new settings can take effect
        $templateMgr = TemplateManager::getManager(Application::get()->getRequest());
        $templateMgr->clearTemplateCache();
        $templateMgr->clearCssCache();

        $data = array_merge(
            $selectedTheme->getOptionValues(\PKP\core\PKPApplication::SITE_CONTEXT_ID),
            ['themePluginPath' => $themePluginPath]
        );

        ksort($data);

        return response()->json($data, Response::HTTP_OK);
    }
}
