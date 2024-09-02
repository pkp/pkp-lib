<?php

/**
 * @file pages/management/ManagementHandler.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ManagementHandler
 *
 * @ingroup pages_management
 *
 * @brief Base class for all management page handlers.
 */

namespace PKP\pages\management;

use APP\components\forms\context\AppearanceAdvancedForm;
use APP\components\forms\context\AppearanceSetupForm;
use APP\components\forms\context\DoiSetupSettingsForm;
use APP\components\forms\context\LicenseForm;
use APP\components\forms\context\MastheadForm;
use APP\core\Application;

use APP\core\Request;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\announcement\Announcement;
use PKP\components\forms\announcement\PKPAnnouncementForm;
use PKP\components\forms\context\PKPAnnouncementSettingsForm;
use PKP\components\forms\context\PKPAppearanceMastheadForm;
use PKP\components\forms\context\PKPContactForm;
use PKP\components\forms\context\PKPContextStatisticsForm;
use PKP\components\forms\context\PKPDateTimeForm;
use PKP\components\forms\context\PKPDoiRegistrationSettingsForm;
use PKP\components\forms\context\PKPDoiSetupSettingsForm;
use PKP\components\forms\context\PKPEmailSetupForm;
use PKP\components\forms\context\PKPInformationForm;
use PKP\components\forms\context\PKPListsForm;
use PKP\components\forms\context\PKPMastheadForm;
use PKP\components\forms\context\PKPNotifyUsersForm;
use PKP\components\forms\context\PKPPaymentSettingsForm;
use PKP\components\forms\context\PKPPrivacyForm;
use PKP\components\forms\context\PKPReviewSetupForm;
use PKP\components\forms\context\PKPSearchIndexingForm;
use PKP\components\forms\context\PKPThemeForm;
use PKP\components\forms\context\PKPUserAccessForm;
use PKP\components\forms\emailTemplate\EmailTemplateForm;
use PKP\components\forms\highlight\HighlightForm;
use PKP\components\forms\submission\SubmissionGuidanceSettings;
use PKP\components\listPanels\HighlightsListPanel;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\mail\Mailable;
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
            case 'manageEmails':
                $this->manageEmails($args, $request);
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
            case 'institutions':
                $this->institutions($args, $request);
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

        $apiUrl = $this->getContextApiUrl($request);
        $publicFileApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_uploadPublicFile');

        $locales = $this->getSupportedFormLocales($context);

        $contactForm = new PKPContactForm($apiUrl, $locales, $context);
        $mastheadForm = new MastheadForm($apiUrl, $locales, $context, $publicFileApiUrl);

        $templateMgr->setState([
            'components' => [
                PKPContactForm::FORM_CONTACT => $contactForm->getConfig(),
                MastheadForm::FORM_MASTHEAD => $mastheadForm->getConfig(),
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
            $userGroups = Repo::userGroup()->getByRoleIds([Role::ROLE_ID_SITE_ADMIN], PKPApplication::SITE_CONTEXT_ID);
            $adminUserGroup = $userGroups->first();

            $siteAdmin = Repo::user()->getCollector()
                ->filterByUserGroupIds([$adminUserGroup->getId()])
                ->getMany()
                ->first();
            $templateMgr->assign('siteAdmin', $siteAdmin);
        }

        $templateMgr->assign('pageTitle', __('manager.setup'));

        $templateMgr->registerClass(PKPContactForm::class, PKPContactForm::class); // FORM_CONTACT
        $templateMgr->registerClass(PKPMastheadForm::class, PKPMastheadForm::class); // FORM_MASTHEAD

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

        $contextApiUrl = $this->getContextApiUrl($request);
        $themeApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/theme');
        $temporaryFileApiUrl = $this->getTemporaryFileApiUrl($context);
        $publicFileApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_uploadPublicFile');

        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getContextFilesPath($context->getId());

        $locales = $this->getSupportedFormLocales($context);

        $announcementSettingsForm = new PKPAnnouncementSettingsForm($contextApiUrl, $locales, $context);
        $appearanceAdvancedForm = new AppearanceAdvancedForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl, $publicFileApiUrl);
        $appearanceSetupForm = new AppearanceSetupForm($contextApiUrl, $locales, $context, $baseUrl, $temporaryFileApiUrl, $publicFileApiUrl);
        $appearanceMastheadForm = new PKPAppearanceMastheadForm($contextApiUrl, $locales, $context);
        $informationForm = $this->getInformationForm($contextApiUrl, $locales, $context, $publicFileApiUrl);
        $listsForm = new PKPListsForm($contextApiUrl, $locales, $context);
        $privacyForm = new PKPPrivacyForm($contextApiUrl, $locales, $context, $publicFileApiUrl);
        $themeForm = new PKPThemeForm($themeApiUrl, $locales, $context);
        $dateTimeForm = new PKPDateTimeForm($contextApiUrl, $locales, $context);

        $highlightsListPanel = $this->getHighlightsListPanel();

        $templateMgr->setConstants([
            'FORM_ANNOUNCEMENT_SETTINGS' => $announcementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS,
        ]);

        $components = [
            $announcementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS => $announcementSettingsForm->getConfig(),
            $appearanceAdvancedForm::FORM_APPEARANCE_ADVANCED => $appearanceAdvancedForm->getConfig(),
            $appearanceSetupForm::FORM_APPEARANCE_SETUP => $appearanceSetupForm->getConfig(),
            $appearanceMastheadForm::FORM_APPEARANCE_MASTHEAD => $appearanceMastheadForm->getConfig(),
            $listsForm::FORM_LISTS => $listsForm->getConfig(),
            $privacyForm::FORM_PRIVACY => $privacyForm->getConfig(),
            $themeForm::FORM_THEME => $themeForm->getConfig(),
            $dateTimeForm::FORM_DATE_TIME => $dateTimeForm->getConfig(),
            $highlightsListPanel->id => $highlightsListPanel->getConfig(),
        ];

        if ($informationForm) {
            $components[$informationForm::FORM_INFORMATION] = $informationForm->getConfig();
            $templateMgr->registerClass($informationForm::class, $informationForm::class);
        }

        $templateMgr->setState([
            'components' => $components,
            'announcementsNavLink' => [
                'name' => __('announcement.announcements'),
                'url' => $router->url($request, null, 'management', 'settings', ['announcements']),
                'isCurrent' => false,
                'icon' => 'Announcements',
            ],
        ]);

        $templateMgr->assign([
            'includeInformationForm' => (bool) $informationForm,
            'pageTitle' => __('manager.website.title'),
        ]);

        $templateMgr->registerClass($announcementSettingsForm::class, $announcementSettingsForm::class);
        $templateMgr->registerClass($appearanceAdvancedForm::class, $appearanceAdvancedForm::class);
        $templateMgr->registerClass($appearanceSetupForm::class, $appearanceSetupForm::class);
        $templateMgr->registerClass($appearanceMastheadForm::class, $appearanceMastheadForm::class);
        $templateMgr->registerClass($listsForm::class, $listsForm::class);
        $templateMgr->registerClass($privacyForm::class, $privacyForm::class);
        $templateMgr->registerClass($themeForm::class, $themeForm::class);
        $templateMgr->registerClass($dateTimeForm::class, $dateTimeForm::class);

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

        $contextApiUrl = $this->getContextApiUrl($request);

        $locales = $this->getSupportedFormLocales($context);

        $disableSubmissionsForm = new \PKP\components\forms\context\PKPDisableSubmissionsForm($contextApiUrl, $locales, $context);
        $emailSetupForm = $this->getEmailSetupForm($contextApiUrl, $locales, $context);
        $metadataSettingsForm = new \APP\components\forms\context\MetadataSettingsForm($contextApiUrl, $context);
        $submissionGuidanceSettingsForm = new SubmissionGuidanceSettings($contextApiUrl, $locales, $context);

        $templateMgr->setState([
            'components' => [
                $disableSubmissionsForm::FORM_DISABLE_SUBMISSIONS => $disableSubmissionsForm->getConfig(),
                $emailSetupForm->id => $emailSetupForm->getConfig(),
                $metadataSettingsForm::FORM_METADATA_SETTINGS => $metadataSettingsForm->getConfig(),
                $submissionGuidanceSettingsForm->id => $submissionGuidanceSettingsForm->getConfig(),
            ],
        ]);

        $templateMgr->assign([
            'pageTitle' => __('manager.workflow.title'),
            'hasReviewStage' => $this->hasReviewStage(),
        ]);
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

        $apiUrl = $this->getContextApiUrl($request);
        $doiRegistrationSettingsApiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/registrationAgency');
        $sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');
        $paymentsUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_payments');

        $locales = $this->getSupportedFormLocales($context);
        $site = $request->getSite();

        $licenseForm = new LicenseForm($apiUrl, $locales, $context);
        $doiSetupSettingsForm = new DoiSetupSettingsForm($apiUrl, $locales, $context);
        $doiRegistrationSettingsForm = new PKPDoiRegistrationSettingsForm($doiRegistrationSettingsApiUrl, $locales, $context);
        $searchIndexingForm = new PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);
        $paymentSettingsForm = new PKPPaymentSettingsForm($paymentsUrl, $locales, $context);
        $contextStatisticsForm = new PKPContextStatisticsForm($apiUrl, $locales, $site, $context);
        $displayStatisticsTab = ($site->getData('enableGeoUsageStats') && $site->getData('enableGeoUsageStats') !== 'disabled') ||
            $site->getData('enableInstitutionUsageStats') ||
            ($site->getData('isSushiApiPublic') === null || $site->getData('isSushiApiPublic'));
        $templateMgr->setConstants([
            'FORM_PAYMENT_SETTINGS' => PKPPaymentSettingsForm::FORM_PAYMENT_SETTINGS,
            'FORM_CONTEXT_STATISTICS' => PKPContextStatisticsForm::FORM_CONTEXT_STATISTICS,
            'FORM_DOI_REGISTRATION_SETTINGS' => PKPDoiRegistrationSettingsForm::FORM_DOI_REGISTRATION_SETTINGS,
        ]);

        $templateMgr->setState([
            'components' => [
                LicenseForm::FORM_LICENSE => $licenseForm->getConfig(),
                PKPDoiSetupSettingsForm::FORM_DOI_SETUP_SETTINGS => $doiSetupSettingsForm->getConfig(),
                PKPDoiRegistrationSettingsForm::FORM_DOI_REGISTRATION_SETTINGS => $doiRegistrationSettingsForm->getConfig(),
                PKPSearchIndexingForm::FORM_SEARCH_INDEXING => $searchIndexingForm->getConfig(),
                PKPPaymentSettingsForm::FORM_PAYMENT_SETTINGS => $paymentSettingsForm->getConfig(),
                PKPContextStatisticsForm::FORM_CONTEXT_STATISTICS => $contextStatisticsForm->getConfig(),
            ],
            // Add an institutions link to be added/removed when statistics form is submitted
            'institutionsNavLink' => [
                'name' => __('institution.institutions'),
                'url' => $router->url($request, null, 'management', 'settings', ['institutions']),
                'isCurrent' => false,
                'icon' => 'Institutes',
            ],
        ]);
        $templateMgr->assign([
            'pageTitle' => __('manager.distribution.title'),
            'displayStatisticsTab' => $displayStatisticsTab,
        ]);
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

        $locales = $this->getSupportedFormLocales($context);

        $announcementForm = new PKPAnnouncementForm(
            $apiUrl,
            $locales,
            Repo::announcement()->getFileUploadBaseUrl($context),
            $this->getTemporaryFileApiUrl($context),
            $request->getContext()
        );

        $announcements = Announcement::withContextIds([$request->getContext()->getId()]);

        $itemsMax = $announcements->count();
        $items = Repo::announcement()->getSchemaMap()->summarizeMany(
            $announcements->limit(30)->get()
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
                'items' => $items->values(),
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
     * Display list of institutions
     */
    public function institutions(array $args, Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $apiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $request->getContext()->getPath(), 'institutions');

        $context = $request->getContext();
        $locales = $this->getSupportedFormLocales($context);

        $institutionForm = new \PKP\components\forms\institution\PKPInstitutionForm($apiUrl, $locales);

        $collector = Repo::institution()
            ->getCollector()
            ->filterByContextIds([$request->getContext()->getId()]);

        $itemsMax = $collector->getCount();
        $items = Repo::institution()->getSchemaMap()->summarizeMany(
            $collector->limit(30)->getMany()
        );

        $institutionsListPanel = new \PKP\components\listPanels\PKPInstitutionsListPanel(
            'institutions',
            __('manager.setup.institutions'),
            [
                'apiUrl' => $apiUrl,
                'form' => $institutionForm,
                'getParams' => [
                    'contextIds' => [$request->getContext()->getId()],
                    'count' => 30,
                ],
                'items' => $items->values(),
                'itemsMax' => $itemsMax,
            ]
        );

        $templateMgr->setState([
            'components' => [
                $institutionsListPanel->id => $institutionsListPanel->getConfig(),
            ],
        ]);

        $templateMgr->assign([
            'pageTitle' => __('manager.setup.institutions'),
        ]);

        $templateMgr->display('management/institutions.tpl');
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

        $apiUrl = $this->getContextApiUrl($request);
        $notifyUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_email');

        $locales = $this->getSupportedFormLocales($context);

        $userAccessForm = new \APP\components\forms\context\UserAccessForm($apiUrl, $context);
        $isBulkEmailsEnabled = in_array($context->getId(), (array) $request->getSite()->getData('enableBulkEmails'));
        $notifyUsersForm = $isBulkEmailsEnabled ? new PKPNotifyUsersForm($notifyUrl, $context) : null;
        // TODO: See if it needs locales
        $orcidSettingsForm = new \PKP\components\forms\context\OrcidSettingsForm($apiUrl, $locales, $context);

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
                $userAccessForm::FORM_USER_ACCESS => $userAccessForm->getConfig(),
                PKPNotifyUsersForm::FORM_NOTIFY_USERS => $notifyUsersForm ? $notifyUsersForm->getConfig() : null,
                $orcidSettingsForm->id => $orcidSettingsForm->getConfig(),
            ],
        ]);

        $templateMgr->registerClass(PKPNotifyUsersForm::class, PKPNotifyUsersForm::class);
        $templateMgr->registerClass(PKPUserAccessForm::class, PKPUserAccessForm::class);
        $templateMgr->registerClass(PKPNotifyUsersForm::class, PKPNotifyUsersForm::class);
        $templateMgr->registerClass($orcidSettingsForm::class, $orcidSettingsForm::class);
        $templateMgr->display('management/access.tpl');
    }

    /**
     * Display the page to manage emails
     */
    public function manageEmails(array $args, Request $request): void
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $context = $request->getContext();
        $emailTemplatesApiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'emailTemplates');
        $mailablesApiUrl = $request->getDispatcher()->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'mailables');

        $templateMgr->assign([
            'pageComponent' => 'ManageEmailsPage',
            'pageTitle' => __('manager.manageEmails'),
        ]);

        $templateMgr->setState([
            'fromFilters' => $this->getEmailFromFilters(),
            'groupFilters' => $this->getEmailGroupFilters(),
            'i18nRemoveTemplate' => __('manager.mailables.removeTemplate'),
            'i18nRemoveTemplateMessage' => __('manager.mailables.removeTemplate.confirm'),
            'i18nResetTemplate' => __('manager.mailables.resetTemplate'),
            'i18nResetTemplateMessage' => __('manager.mailables.resetTemplate.confirm'),
            'i18nResetAll' => __('manager.emails.resetAll'),
            'i18nResetAllMessage' => __('manager.emails.resetAll.message'),
            'mailables' => Repo::mailable()->getMany($context, null, false, true)
                ->map(fn (string $class) => Repo::mailable()->summarizeMailable($class))
                ->sortBy('name')
                ->values(),
            'mailablesApiUrl' => $mailablesApiUrl,
            'templatesApiUrl' => $emailTemplatesApiUrl,
            'templateForm' => $this->getEmailTemplateForm($context, $emailTemplatesApiUrl)->getConfig(),
            'toFilters' => $this->getEmailToFilters(),
        ]);

        $templateMgr->display('management/manageEmails.tpl');
    }

    protected function getEmailTemplateForm(Context $context, string $apiUrl): EmailTemplateForm
    {
        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        return new EmailTemplateForm($apiUrl, $locales);
    }

    protected function getEmailGroupFilters(): array
    {
        return [
            Mailable::GROUP_SUBMISSION => __('submission.submission'),
            Mailable::GROUP_REVIEW => __('submission.review'),
            Mailable::GROUP_COPYEDITING => __('submission.copyediting'),
            Mailable::GROUP_PRODUCTION => __('submission.production'),
            Mailable::GROUP_OTHER => __('common.other'),
        ];
    }

    protected function getEmailFromFilters(): array
    {
        return [
            Role::ROLE_ID_SUB_EDITOR => __('user.role.editor'),
            Role::ROLE_ID_REVIEWER => __('user.role.reviewer'),
            Role::ROLE_ID_ASSISTANT => __('user.role.assistant'),
            Role::ROLE_ID_READER => __('user.role.reader'),
            Mailable::FROM_SYSTEM => __('mailable.system'),
        ];
    }

    protected function getEmailToFilters(): array
    {
        return [
            Role::ROLE_ID_SUB_EDITOR => __('user.role.editor'),
            Role::ROLE_ID_REVIEWER => __('user.role.reviewer'),
            Role::ROLE_ID_ASSISTANT => __('user.role.assistant'),
            Role::ROLE_ID_AUTHOR => __('user.role.author'),
            Role::ROLE_ID_READER => __('user.role.reader'),
        ];
    }

    protected function getEmailSetupForm(string $contextApiUrl, array $locales, Context $context): PKPEmailSetupForm
    {
        return new PKPEmailSetupForm($contextApiUrl, $locales, $context);
    }

    protected function hasReviewStage(): bool
    {
        return count(
            array_intersect(
                [WORKFLOW_STAGE_ID_INTERNAL_REVIEW, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW],
                Application::get()->getApplicationStages()
            )
        );
    }

    protected function getInformationForm(string $contextApiUrl, array $locales, Context $context, string $publicFileApiUrl): ?PKPInformationForm
    {
        return new PKPInformationForm(
            $contextApiUrl,
            $locales,
            $context,
            $publicFileApiUrl
        );
    }

    /**
     * Return Context API Url
     */
    protected function getContextApiUrl(PKPRequest $request): string
    {
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        return $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
    }

    /**
     * Return context's supportedFormLocales
     */
    protected function getSupportedFormLocales(Context $context): array
    {
        $locales = $context->getSupportedFormLocaleNames();
        return array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);
    }

    /**
     * Add support for review related forms in workflow.
     */
    protected function addReviewFormWorkflowSupport(PKPRequest $request): void
    {
        $templateMgr = TemplateManager::getManager($request);

        $context = $request->getContext();

        $contextApiUrl = $this->getContextApiUrl($request);

        $locales = $this->getSupportedFormLocales($context);

        $reviewGuidanceForm = new \APP\components\forms\context\ReviewGuidanceForm($contextApiUrl, $locales, $context);
        $reviewSetupForm = new PKPReviewSetupForm($contextApiUrl, $locales, $context);

        $components = $templateMgr->getState('components');
        $components[$reviewGuidanceForm->id] = $reviewGuidanceForm->getConfig();
        $components[$reviewSetupForm->id] = $reviewSetupForm->getConfig();
        $templateMgr->setState(['components' => $components]);
    }

    /**
     * Get the HighlightsListPanel
     */
    protected function getHighlightsListPanel(): HighlightsListPanel
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $apiUrl = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_API,
            $context->getPath(),
            'highlights'
        );

        $highlightForm = new HighlightForm(
            $apiUrl,
            Repo::highlight()->getFileUploadBaseUrl($context),
            $this->getTemporaryFileApiUrl($context),
            $context
        );

        $items = Repo::highlight()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        return new HighlightsListPanel(
            'highlights',
            __('common.highlights'),
            [
                'apiUrl' => $apiUrl,
                'form' => $highlightForm,
                'items' => Repo::highlight()
                    ->getSchemaMap()
                    ->summarizeMany($items)
                    ->values(),
                'itemsMax' => $items->count(),
            ]
        );
    }

    /**
     * Get the API url for uploading temporary files
     */
    protected function getTemporaryFileApiUrl(Context $context): string
    {
        return Application::get()
            ->getDispatcher()
            ->url(
                Application::get()->getRequest(),
                Application::ROUTE_API,
                $context->getPath(),
                'temporaryFiles'
            );
    }
}
