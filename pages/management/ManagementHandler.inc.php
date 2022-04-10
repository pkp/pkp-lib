<?php

/**
 * @file pages/management/ManagementHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManagementHandler
 * @ingroup pages_management
 *
 * @brief Base class for all management page handlers.
 */

use APP\components\forms\context\DoiSetupSettingsForm;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\components\forms\context\PKPNotifyUsersForm;
use PKP\config\Config;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\site\VersionCheck;

class ManagementHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    //
    // Overridden methods from Handler
    //
    /**
     * @see PKPHandler::initialize()
     */
    public function initialize($request)
    {
        parent::initialize($request);

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign('pageComponent', 'SettingsPage');
    }

    /**
     * @see PKPHandler::authorize()
     *
     * @param PKPRequest $request
     * @param array $args
     * @param array $roleAssignments
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new ContextAccessPolicy($request, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Route requests to the appropriate operation.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function settings($args, $request)
    {
        $path = array_shift($args);
        switch ($path) {
            case 'index':
            case '':
            case 'context':
                $this->context($args, $request);
                break;
            case 'website':
                $this->website($args, $request);
                break;
            case 'workflow':
                $this->workflow($args, $request);
                break;
            case 'distribution':
                $this->distribution($args, $request);
                break;
            case 'access':
                $this->access($args, $request);
                break;
            case 'announcements':
                $this->announcements($args, $request);
                break;
            default:
                assert(false);
                $request->getDispatcher()->handle404();
        }
    }

    /**
     * Display settings for a journal/press
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function context($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $publicFileApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_uploadPublicFile');

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $contactForm = new PKP\components\forms\context\PKPContactForm($apiUrl, $locales, $context);
        $mastheadForm = new APP\components\forms\context\MastheadForm($apiUrl, $locales, $context, $publicFileApiUrl);

        $templateMgr->setState([
            'components' => [
                FORM_CONTACT => $contactForm->getConfig(),
                FORM_MASTHEAD => $mastheadForm->getConfig(),
            ],
        ]);

        // Interact with the beacon (if enabled) and determine if a new version exists
        $latestVersion = VersionCheck::checkIfNewVersionExists();

        // Display a warning message if there is a new version of OJS available
        if (Config::getVar('general', 'show_upgrade_warning') && $latestVersion) {
            $currentVersion = VersionCheck::getCurrentDBVersion();
            $templateMgr->assign([
                'newVersionAvailable' => true,
                'currentVersion' => $currentVersion->getVersionString(),
                'latestVersion' => $latestVersion,
            ]);

            // Get contact information for site administrator
            $userGroupDao = DAORegistry::getDAO('UserGroupDAO');
            $userGroups = $userGroupDao->getByRoleId(PKPApplication::CONTEXT_SITE, Role::ROLE_ID_SITE_ADMIN);
            $adminUserGroup = $userGroups->next();

            $collector = Repo::user()->getCollector();
            $collector->filterByUserGroupIds([$adminUserGroup->getId()]);
            $siteAdmin = Repo::user()->getMany($collector)->first();
            $templateMgr->assign('siteAdmin', $siteAdmin);
        }

        $templateMgr->assign('pageTitle', __('manager.setup'));
        $templateMgr->display('management/context.tpl');
    }

    /**
     * Display website settings
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function website($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();
        $router = $request->getRouter();

        $contextApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $themeApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/theme');
        $temporaryFileApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'temporaryFiles');
        $publicFileApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_uploadPublicFile');

        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId());

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $announcementSettingsForm = new \PKP\components\forms\context\PKPAnnouncementSettingsForm($contextApiUrl, $locales, $context);
        $appearanceAdvancedForm = new \APP\components\forms\context\AppearanceAdvancedForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl, $publicFileApiUrl);
        $appearanceSetupForm = new \APP\components\forms\context\AppearanceSetupForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl, $publicFileApiUrl);
        $informationForm = new \PKP\components\forms\context\PKPInformationForm($contextApiUrl, $locales, $context, $publicFileApiUrl);
        $listsForm = new \PKP\components\forms\context\PKPListsForm($contextApiUrl, $locales, $context);
        $privacyForm = new \PKP\components\forms\context\PKPPrivacyForm($contextApiUrl, $locales, $context, $publicFileApiUrl);
        $themeForm = new \PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales, $context);
        $dateTimeForm = new \PKP\components\forms\context\PKPDateTimeForm($contextApiUrl, $locales, $context);

        $templateMgr->setConstants([
            'FORM_ANNOUNCEMENT_SETTINGS' => FORM_ANNOUNCEMENT_SETTINGS,
        ]);

        $templateMgr->setState([
            'components' => [
                FORM_ANNOUNCEMENT_SETTINGS => $announcementSettingsForm->getConfig(),
                FORM_APPEARANCE_ADVANCED => $appearanceAdvancedForm->getConfig(),
                FORM_APPEARANCE_SETUP => $appearanceSetupForm->getConfig(),
                FORM_INFORMATION => $informationForm->getConfig(),
                FORM_LISTS => $listsForm->getConfig(),
                FORM_PRIVACY => $privacyForm->getConfig(),
                FORM_THEME => $themeForm->getConfig(),
                FORM_DATE_TIME => $dateTimeForm->getConfig(),
            ],
            'announcementsNavLink' => [
                'name' => __('announcement.announcements'),
                'url' => $router->url($request, null, 'management', 'settings', 'announcements'),
                'isCurrent' => false,
            ],
        ]);

        $templateMgr->assign('pageTitle', __('manager.website.title'));
        $templateMgr->display('management/website.tpl');
    }

    /**
     * Display workflow settings
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function workflow($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $contextApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $emailTemplatesApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'emailTemplates');

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $authorGuidelinesForm = new \PKP\components\forms\context\PKPAuthorGuidelinesForm($contextApiUrl, $locales, $context);
        $metadataSettingsForm = new \APP\components\forms\context\MetadataSettingsForm($contextApiUrl, $context);
        $disableSubmissionsForm = new \PKP\components\forms\context\PKPDisableSubmissionsForm($contextApiUrl, $locales, $context);
        $emailSetupForm = new \PKP\components\forms\context\PKPEmailSetupForm($contextApiUrl, $locales, $context);
        $reviewGuidanceForm = new \APP\components\forms\context\ReviewGuidanceForm($contextApiUrl, $locales, $context);
        $reviewSetupForm = new \PKP\components\forms\context\PKPReviewSetupForm($contextApiUrl, $locales, $context);
        $submissionsNotificationsForm = new \PKP\components\forms\context\PKPSubmissionsNotificationsForm($contextApiUrl, $locales, $context);

        $emailTemplatesListPanel = new \APP\components\listPanels\EmailTemplatesListPanel(
            'emailTemplates',
            __('manager.emails.emailTemplates'),
            $locales,
            [
                'apiUrl' => $emailTemplatesApiUrl,
                'count' => 200,
                'items' => [],
                'itemsMax' => 0,
                'lazyLoad' => true,
            ]
        );

        $templateMgr->setState([
            'components' => [
                FORM_AUTHOR_GUIDELINES => $authorGuidelinesForm->getConfig(),
                FORM_METADATA_SETTINGS => $metadataSettingsForm->getConfig(),
                FORM_DISABLE_SUBMISSIONS => $disableSubmissionsForm->getConfig(),
                FORM_EMAIL_SETUP => $emailSetupForm->getConfig(),
                FORM_REVIEW_GUIDANCE => $reviewGuidanceForm->getConfig(),
                FORM_REVIEW_SETUP => $reviewSetupForm->getConfig(),
                FORM_SUBMISSIONS_NOTIFICATIONS => $submissionsNotificationsForm->getConfig(),
                'emailTemplates' => $emailTemplatesListPanel->getConfig(),
            ],
        ]);
        $templateMgr->assign('pageTitle', __('manager.workflow.title'));
    }

    /**
     * Display distribution settings
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function distribution($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');
        $paymentsUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_payments');

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $licenseForm = new \APP\components\forms\context\LicenseForm($apiUrl, $locales, $context);
        $doiSetupSettingsForm = new DoiSetupSettingsForm($apiUrl, $locales, $context);
        $doiRegistrationSettingsForm = new \PKP\components\forms\context\PKPDoiRegistrationSettingsForm($apiUrl, $locales, $context);
        $searchIndexingForm = new \PKP\components\forms\context\PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);

        $paymentSettingsForm = new \PKP\components\forms\context\PKPPaymentSettingsForm($paymentsUrl, $locales, $context);
        $templateMgr->setConstants([
            'FORM_PAYMENT_SETTINGS' => FORM_PAYMENT_SETTINGS,
        ]);

        $templateMgr->setState([
            'components' => [
                FORM_LICENSE => $licenseForm->getConfig(),
                \PKP\components\forms\context\PKPDoiSetupSettingsForm::FORM_DOI_SETUP_SETTINGS => $doiSetupSettingsForm->getConfig(),
                \PKP\components\forms\context\PKPDoiRegistrationSettingsForm::FORM_DOI_REGISTRATION_SETTINGS => $doiRegistrationSettingsForm->getConfig(),
                FORM_SEARCH_INDEXING => $searchIndexingForm->getConfig(),
                FORM_PAYMENT_SETTINGS => $paymentSettingsForm->getConfig(),
            ],
        ]);
        $templateMgr->assign('pageTitle', __('manager.distribution.title'));
    }

    /**
     * Display list of announcements and announcement types
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function announcements($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $apiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $request->getContext()->getPath(), 'announcements');
        $context = $request->getContext();

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $announcementForm = new \PKP\components\forms\announcement\PKPAnnouncementForm($apiUrl, $locales, $request->getContext());

        $collector = Repo::announcement()
            ->getCollector()
            ->filterByContextIds([$request->getContext()->getId()]);

        $itemsMax = Repo::announcement()->getCount($collector);
        $items = Repo::announcement()->getSchemaMap()->summarizeMany(
            Repo::announcement()->getMany($collector->limit(30))
        );

        $announcementsListPanel = new \PKP\components\listPanels\PKPAnnouncementsListPanel(
            'announcements',
            __('manager.setup.announcements'),
            [
                'apiUrl' => $apiUrl,
                'form' => $announcementForm,
                'getParams' => [
                    'contextIds' => [$request->getContext()->getId()],
                    'count' => 30,
                ],
                'items' => $items,
                'itemsMax' => $itemsMax,
            ]
        );

        $templateMgr->setState([
            'components' => [
                $announcementsListPanel->id => $announcementsListPanel->getConfig(),
            ],
        ]);

        $templateMgr->assign([
            'pageTitle' => __('manager.setup.announcements'),
        ]);

        $templateMgr->display('management/announcements.tpl');
    }

    /**
     * Display Access and Security page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function access($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $notifyUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_email');
        $progressUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_email/{queueId}');

        $userAccessForm = new \APP\components\forms\context\UserAccessForm($apiUrl, $context);
        $isBulkEmailsEnabled = in_array($context->getId(), (array) $request->getSite()->getData('enableBulkEmails'));
        $notifyUsersForm = $isBulkEmailsEnabled ? new PKPNotifyUsersForm($notifyUrl, $context) : null;

        $templateMgr->assign([
            'pageComponent' => 'AccessPage',
            'pageTitle' => __('navigation.access'),
            'enableBulkEmails' => $isBulkEmailsEnabled,
        ]);

        $templateMgr->setConstants([
            'FORM_NOTIFY_USERS' => PKPNotifyUsersForm::FORM_NOTIFY_USERS,
        ]);

        $templateMgr->setState([
            'components' => [
                FORM_USER_ACCESS => $userAccessForm->getConfig(),
                PKPNotifyUsersForm::FORM_NOTIFY_USERS => $notifyUsersForm ? $notifyUsersForm->getConfig() : null,
            ],
            'progressUrl' => $progressUrl,
        ]);

        $templateMgr->display('management/access.tpl');
    }
}
