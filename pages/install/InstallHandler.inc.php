<?php

/**
 * @file pages/install/InstallHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallHandler
 * @ingroup pages_install
 *
 * @brief Handle installation requests.
 */

use APP\core\Application;
use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\facades\Locale;
use PKP\install\form\InstallForm;
use PKP\install\form\UpgradeForm;

class InstallHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * If no context is selected, list all.
     * Otherwise, display the index page for the selected context.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {
        // Make sure errors are displayed to the browser during install.
        @ini_set('display_errors', true);

        $this->validate(null, $request);
        $this->setupTemplate($request);

        if (($setLocale = $request->getUserVar('setLocale')) != null && Locale::isLocaleValid($setLocale)) {
            $request->setCookieVar('currentLocale', $setLocale);
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('installer.appInstallation'),
        ]);

        $installForm = new InstallForm($request);
        $installForm->initData();
        $installForm->display($request);
    }

    /**
     * Redirect to index if system has already been installed.
     *
     * @param PKPRequest $request
     * @param null|mixed $requiredContexts
     */
    public function validate($requiredContexts = null, $request = null)
    {
        if (Application::isInstalled()) {
            $request->redirect(null, 'index');
        }
    }

    /**
     * Execute installer.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function install($args, $request)
    {
        $this->validate(null, $request);
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);

        $installForm = new InstallForm($request);
        $installForm->readInputData();

        if ($installForm->validate()) {
            $templateMgr->assign([
                'pageTitle' => __('installer.installationComplete'),
            ]);
            $installForm->execute();
        } else {
            $templateMgr->assign([
                'pageTitle' => __('installer.appInstallation'),
            ]);
            $errors = $installForm->getErrorsArray();
            $error = array_shift($errors);
            $installForm->installError($error, false);
        }
    }

    /**
     * Display upgrade form.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function upgrade($args, $request)
    {
        $this->validate(null, $request);
        $this->setupTemplate($request);

        if (($setLocale = $request->getUserVar('setLocale')) != null && Locale::isLocaleValid($setLocale)) {
            $request->setCookieVar('currentLocale', $setLocale);
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('installer.upgradeApplication'),
        ]);

        $installForm = new UpgradeForm($request);
        $installForm->initData();
        $installForm->display($request);
    }

    /**
     * Execute upgrade.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function installUpgrade($args, $request)
    {
        $this->validate(null, $request);
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('installer.upgradeApplication'),
        ]);

        $installForm = new UpgradeForm($request);
        $installForm->readInputData();

        if ($installForm->validate()) {
            $installForm->execute();
        } else {
            $installForm->display($request);
        }
    }
}
