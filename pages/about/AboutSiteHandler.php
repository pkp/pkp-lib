<?php

/**
 * @file pages/about/AboutSiteHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AboutSiteHandler
 * @ingroup pages_about
 *
 * @brief Handle requests for site-wide about functions.
 */

namespace PKP\pages\about;

use APP\core\Application;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;

class AboutSiteHandler extends Handler
{
    /**
     * Display aboutThisPublishingSystem page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function aboutThisPublishingSystem($args, $request)
    {
        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $version = $versionDao->getCurrentVersion();

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'appVersion' => $version->getVersionString(false),
            'contactUrl' => $request->getDispatcher()->url(
                Application::get()->getRequest(),
                PKPApplication::ROUTE_PAGE,
                null,
                'about',
                'contact'
            ),
        ]);

        $templateMgr->display('frontend/pages/aboutThisPublishingSystem.tpl');
    }

    /**
     * Display privacy policy page.
     *
     * @param array $args
     * @param \PKP\core\PKPRequest $request
     */
    public function privacy($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $enableSiteWidePrivacyStatement = Config::getVar('general', 'sitewide_privacy_statement');
        if (!$enableSiteWidePrivacyStatement && $context) {
            $privacyStatement = $context->getLocalizedData('privacyStatement');
        } else {
            $privacyStatement = $request->getSite()->getLocalizedData('privacyStatement');
        }
        if (!$privacyStatement) {
            $dispatcher = $this->getDispatcher();
            $dispatcher->handle404();
        }
        $templateMgr->assign('privacyStatement', $privacyStatement);

        $templateMgr->display('frontend/pages/privacy.tpl');
    }
}
