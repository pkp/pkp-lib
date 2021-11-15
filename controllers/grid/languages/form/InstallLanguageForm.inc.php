<?php

/**
 * @file controllers/grid/languages/form/InstallLanguageForm.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class InstallLanguageForm
 * @ingroup controllers_grid_languages_form
 *
 * @brief Form for installing languages.
 */

use APP\template\TemplateManager;
use PKP\facades\Locale;
use PKP\form\Form;
use PKP\i18n\LocaleMetadata;

class InstallLanguageForm extends Form
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct('controllers/grid/languages/installLanguageForm.tpl');
    }

    //
    // Overridden methods from Form.
    //
    /**
     * @copydoc Form::initData()
     */
    public function initData()
    {
        parent::initData();

        $request = Application::get()->getRequest();
        $site = $request->getSite();
        $this->setData('installedLocales', $site->getInstalledLocales());
    }

    /**
     * @copydoc Form::fetch()
     *
     * @param null|mixed $template
     */
    public function fetch($request, $template = null, $display = false)
    {
        $allLocales = array_map(fn(LocaleMetadata $locale) => $locale->name, Locale::getLocales());
        $installedLocales = $this->getData('installedLocales');
        $notInstalledLocales = array_diff(array_keys($allLocales), $installedLocales);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'allLocales' => $allLocales,
            'notInstalledLocales' => $notInstalledLocales,
        ]);

        return parent::fetch($request, $template, $display);
    }

    /**
     * @copydoc Form::readInputData()
     */
    public function readInputData()
    {
        parent::readInputData();

        $request = Application::get()->getRequest();
        $localesToInstall = $request->getUserVar('localesToInstall');
        $this->setData('localesToInstall', $localesToInstall);
    }

    /**
     * @copydoc Form::execute()
     */
    public function execute(...$functionArgs)
    {
        $request = Application::get()->getRequest();
        $site = $request->getSite();
        $localesToInstall = $this->getData('localesToInstall');

        parent::execute(...$functionArgs);

        if (isset($localesToInstall) && is_array($localesToInstall)) {
            $installedLocales = $site->getInstalledLocales();
            $supportedLocales = $site->getSupportedLocales();

            foreach ($localesToInstall as $locale) {
                if (Locale::isLocaleValid($locale) && !in_array($locale, $installedLocales)) {
                    array_push($installedLocales, $locale);
                    // Activate/support by default.
                    if (!in_array($locale, $supportedLocales)) {
                        array_push($supportedLocales, $locale);
                    }
                    Locale::installLocale($locale);
                }
            }

            $site->setInstalledLocales($installedLocales);
            $site->setSupportedLocales($supportedLocales);
            $siteDao = DAORegistry::getDAO('SiteDAO'); /** @var SiteDAO $siteDao */
            $siteDao->updateObject($site);
        }
    }
}
