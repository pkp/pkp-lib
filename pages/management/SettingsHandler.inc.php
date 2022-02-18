<?php

/**
 * @file pages/management/SettingsHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class SettingsHandler
 * @ingroup pages_management
 *
 * @brief Handle requests for settings pages.
 */

// Import the base ManagementHandler.
import('lib.pkp.pages.management.ManagementHandler');

use APP\template\TemplateManager;
use PKP\core\PKPApplication;
use PKP\facades\Locale;
use PKP\security\Role;

class SettingsHandler extends ManagementHandler
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN],
            [
                'access',
            ]
        );
        $this->addRoleAssignment(
            Role::ROLE_ID_MANAGER,
            [
                'settings',
            ]
        );
    }

    /**
     * Add the OPS workflow settings page
     *
     * @param array $args
     * @param Request $request
     */
    public function workflow($args, $request)
    {
        parent::workflow($args, $request);
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $screeningForm = new \APP\components\forms\context\ScreeningForm($apiUrl, $locales, $context);

        // Add forms to the existing settings data
        $settingsData = $templateMgr->getTemplateVars('settingsData');
        $settingsData['components'][$screeningForm->id] = $screeningForm->getConfig();

        $templateMgr->assign('settingsData', $settingsData);
        TemplateManager::getManager($request)->display('management/workflow.tpl');
    }

    /**
     * Add OPS distribution settings
     *
     * @param array $args
     * @param Request $request
     */
    public function distribution($args, $request)
    {
        parent::distribution($args, $request);
        $templateMgr = TemplateManager::getManager($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $accessForm = new \APP\components\forms\context\AccessForm($apiUrl, $locales, $context);

        // Add forms to the existing settings data
        $components = $templateMgr->getState('components');
        $components[$accessForm->id] = $accessForm->getConfig();
        $templateMgr->setState(['components' => $components]);

        $templateMgr->display('management/distribution.tpl');
    }
}
