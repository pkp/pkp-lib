<?php

/**
 * @file pages/admin/AdminHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdminHandler
 *
 * @ingroup pages_admin
 *
 * @brief Handle requests for site administration functions.
 */

namespace PKP\pages\admin;

use APP\components\forms\context\ContextForm;
use APP\core\Application;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PKP\announcement\Announcement;
use PKP\components\forms\announcement\PKPAnnouncementForm;
use PKP\components\forms\context\PKPAnnouncementSettingsForm;
use PKP\components\forms\context\PKPContextForm;
use PKP\components\forms\context\PKPSearchIndexingForm;
use PKP\components\forms\context\PKPThemeForm;
use PKP\components\forms\highlight\HighlightForm;
use PKP\components\forms\site\OrcidSiteSettingsForm;
use PKP\components\forms\site\PKPSiteAppearanceForm;
use PKP\components\forms\site\PKPSiteBulkEmailsForm;
use PKP\components\forms\site\PKPSiteConfigForm;
use PKP\components\forms\site\PKPSiteInformationForm;
use PKP\components\forms\site\PKPSiteStatisticsForm;
use PKP\components\listPanels\HighlightsListPanel;
use PKP\components\listPanels\PKPAnnouncementsListPanel;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPContainer;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\highlight\Collector as HighlightCollector;
use PKP\job\resources\HttpFailedJobResource;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\site\VersionCheck;
use PKP\site\VersionDAO;

class AdminHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN],
            [
                'index',
                'contexts',
                'settings',
                'wizard',
                'systemInfo',
                'phpinfo',
                'expireSessions',
                'clearTemplateCache',
                'clearDataCache',
                'downloadScheduledTaskLogFile',
                'clearScheduledTaskLogFiles',
                'jobs',
                'failedJobs',
                'failedJobDetails',
            ]
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        $returner = parent::authorize($request, $args, $roleAssignments);

        // Admin shouldn't access this page from a specific context
        if ($request->getContext()) {
            return false;
        }

        return $returner;
    }

    /**
     * @copydoc PKPHandler::initialize()
     */
    public function initialize($request)
    {
        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->assign([
            'pageComponent' => 'AdminPage',
        ]);

        if ($request->getRequestedOp() !== 'index') {
            $router = $request->getRouter();
            $templateMgr->assign([
                'breadcrumbs' => [
                    [
                        'id' => 'admin',
                        'url' => $router->url($request, Application::SITE_CONTEXT_PATH, 'admin'),
                        'name' => __('navigation.admin'),
                    ]
                ]
            ]);
        }

        // Interact with the beacon (if enabled) and determine if a new version exists
        $latestVersion = VersionCheck::checkIfNewVersionExists();

        // Display a warning message if there is a new application version available
        if (Config::getVar('general', 'show_upgrade_warning') && $latestVersion) {
            $currentVersion = VersionCheck::getCurrentDBVersion();
            $templateMgr->assign([
                'newVersionAvailable' => true,
                'currentVersion' => $currentVersion,
                'latestVersion' => $latestVersion,
            ]);
        }

        return parent::initialize($request);
    }

    /**
     * Display site admin index page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function index($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'pageTitle' => __('admin.siteAdmin'),
        ]);
        $templateMgr->display('admin/index.tpl');
    }

    /**
     * Display a list of the contexts hosted on the site.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function contexts($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('admin.hostedContexts'),
        ];
        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('admin.hostedContexts'),
        ]);
        $templateMgr->display('admin/contexts.tpl');
    }

    /**
     * Display the administration settings page.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function settings($args, $request)
    {
        $this->setupTemplate($request);
        $site = $request->getSite();
        $dispatcher = $request->getDispatcher();

        $apiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'site');
        $themeApiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'site/theme');
        $temporaryFileApiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'temporaryFiles');
        $announcementsApiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'announcements');

        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath();

        $locales = $site->getSupportedLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $contexts = app()->get('context')->getManySummary();

        $siteAppearanceForm = new PKPSiteAppearanceForm($apiUrl, $locales, $site, $baseUrl, $temporaryFileApiUrl);
        $siteConfigForm = new PKPSiteConfigForm($apiUrl, $locales, $site);
        $siteInformationForm = new PKPSiteInformationForm($apiUrl, $locales, $site);
        $siteBulkEmailsForm = new PKPSiteBulkEmailsForm($apiUrl, $site, $contexts);
        $orcidSettingsForm = new OrcidSiteSettingsForm($apiUrl, $locales, $site);
        $themeForm = new PKPThemeForm($themeApiUrl, $locales);
        $siteStatisticsForm = new PKPSiteStatisticsForm($apiUrl, $locales, $site);
        $highlightsListPanel = $this->getHighlightsListPanel();
        $announcementSettingsForm = new PKPAnnouncementSettingsForm($apiUrl, $locales, $site);
        $announcementsForm = new PKPAnnouncementForm($announcementsApiUrl, $locales, Repo::Announcement()->getFileUploadBaseUrl(), $temporaryFileApiUrl);
        $announcementsListPanel = $this->getAnnouncementsListPanel($announcementsApiUrl, $announcementsForm);

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setConstants([
            'FORM_ANNOUNCEMENT_SETTINGS' => PKPAnnouncementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS,
        ]);

        $templateMgr->setState([
            'announcementsEnabled' => (bool) $site->getData('enableAnnouncements'),
            'components' => [
                $announcementsListPanel->id => $announcementsListPanel->getConfig(),
                $siteAppearanceForm::FORM_SITE_APPEARANCE => $siteAppearanceForm->getConfig(),
                $siteConfigForm::FORM_SITE_CONFIG => $siteConfigForm->getConfig(),
                $siteInformationForm::FORM_SITE_INFO => $siteInformationForm->getConfig(),
                $siteBulkEmailsForm::FORM_SITE_BULK_EMAILS => $siteBulkEmailsForm->getConfig(),
                $orcidSettingsForm->id => $orcidSettingsForm->getConfig(),
                $themeForm::FORM_THEME => $themeForm->getConfig(),
                $siteStatisticsForm::FORM_SITE_STATISTICS => $siteStatisticsForm->getConfig(),
                $highlightsListPanel->id => $highlightsListPanel->getConfig(),
                $announcementSettingsForm::FORM_ANNOUNCEMENT_SETTINGS => $announcementSettingsForm->getConfig(),
            ],
        ]);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'settings',
            'name' => __('admin.siteSettings'),
        ];
        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('admin.siteSettings'),
            'componentAvailability' => $this->siteSettingsAvailability(),
        ]);

        $templateMgr->display('admin/settings.tpl');
    }

    /**
     * Business logic for site settings single/multiple contexts availability
     *
     */
    private function siteSettingsAvailability(): array
    {
        // The multi context UI is also displayed when the journal has no contexts
        $isMultiContextSite = app()->get('context')->getCount() !== 1;
        return [
            'siteSetup' => true,
            'languages' => true,
            'bulkEmails' => true,
            'statistics' => true,
            'siteAppearance' => $isMultiContextSite,
            'sitePlugins' => $isMultiContextSite,
            'siteConfig' => $isMultiContextSite,
            'siteInfo' => $isMultiContextSite,
            'navigationMenus' => $isMultiContextSite,
            'highlights' => $isMultiContextSite,
            'siteTheme' => $isMultiContextSite,
            'siteAppearanceSetup' => $isMultiContextSite,
            'announcements' => $isMultiContextSite,
            'orcidSiteSettings' => $isMultiContextSite,
        ];
    }

    /**
     * Display a settings wizard for a journal
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function wizard($args, $request)
    {
        $this->setupTemplate($request);
        $router = $request->getRouter();
        $dispatcher = $request->getDispatcher();

        if (!isset($args[0]) || !ctype_digit((string) $args[0])) {
            $request->getDispatcher()->handle404();
        }

        $contextService = app()->get('context');
        $context = $contextService->get((int) $args[0]);

        if (empty($context)) {
            $request->getDispatcher()->handle404();
        }

        $apiUrl = $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $themeApiUrl = $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/theme');
        $sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $contextForm = new ContextForm($apiUrl, $locales, $request->getBaseUrl(), $context);
        $themeForm = new PKPThemeForm($themeApiUrl, $locales, $context);
        $indexingForm = new PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);

        $components = [
            $contextForm::FORM_CONTEXT => $contextForm->getConfig(),
            $indexingForm::FORM_SEARCH_INDEXING => $indexingForm->getConfig(),
            $themeForm::FORM_THEME => $themeForm->getConfig(),
        ];

        $bulkEmailsEnabled = in_array($context->getId(), (array) $request->getSite()->getData('enableBulkEmails'));
        if ($bulkEmailsEnabled) {
            $userGroups = Repo::userGroup()->getCollector()
                ->filterByContextIds([$context->getId()])
                ->getMany();

            $restrictBulkEmailsForm = new \PKP\components\forms\context\PKPRestrictBulkEmailsForm($apiUrl, $context, $userGroups);
            $components[$restrictBulkEmailsForm->id] = $restrictBulkEmailsForm->getConfig();
        }

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setState([
            'components' => $components,
        ]);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'contexts',
            'name' => __('admin.hostedContexts'),
            'url' => $router->url($request, Application::SITE_CONTEXT_PATH, 'admin', 'contexts'),
        ];
        $breadcrumbs[] = [
            'id' => 'wizard',
            'name' => __('manager.settings.wizard'),
        ];

        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'bulkEmailsEnabled' => $bulkEmailsEnabled,
            'editContext' => $context,
            'pageTitle' => __('manager.settings.wizard'),
        ]);

        $templateMgr->registerClass(PKPSearchIndexingForm::class, PKPSearchIndexingForm::class); // FORM_SEARCH_INDEXING
        $templateMgr->registerClass(PKPContextForm::class, PKPContextForm::class); // FORM_CONTEXT

        $templateMgr->display('admin/contextSettings.tpl');
    }

    /**
     * Show system information summary.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function systemInfo($args, $request)
    {
        $this->setupTemplate($request);

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $currentVersion = $versionDao->getCurrentVersion();

        if ($request->getUserVar('versionCheck')) {
            $latestVersionInfo = VersionCheck::getLatestVersion();
        } else {
            $latestVersionInfo = null;
        }

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $versionHistory = $versionDao->getVersionHistory();
        $pdo = DB::getPDO();

        $serverInfo = [
            'admin.server.platform' => PHP_OS,
            'admin.server.phpVersion' => phpversion(),
            'admin.server.apacheVersion' => $_SERVER['SERVER_SOFTWARE'],
            'admin.server.dbDriver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
            'admin.server.dbVersion' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
        ];

        $templateMgr = TemplateManager::getManager($request);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'wizard',
            'name' => __('admin.systemInformation'),
        ];

        $templateMgr->assign([
            'breadcrumbs' => $breadcrumbs,
            'currentVersion' => $currentVersion,
            'latestVersionInfo' => $latestVersionInfo,
            'pageTitle' => __('admin.systemInformation'),
            'versionHistory' => $versionHistory,
            'serverInfo' => $serverInfo,
            'configData' => Config::getData(),
        ]);

        $templateMgr->display('admin/systemInfo.tpl');
    }

    /**
     * Show full PHP configuration information.
     */
    public function phpinfo()
    {
        phpinfo();
    }

    /**
     * Expire all user sessions (will log out all users currently logged in).
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function expireSessions($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        Application::get()->getRequest()->getSessionGuard()->removeAllSession();
        $request->redirect(null, 'login');
    }

    /**
     * Clear compiled templates.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function clearTemplateCache($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->clearTemplateCache();
        $templateMgr->clearCssCache();
        $request->redirect(null, 'admin');
    }

    /**
     * Clear the data cache.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function clearDataCache($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        // Clear Laravel caches
        $cacheManager = PKPContainer::getInstance()['cache'];
        $cacheManager->store()->flush();

        $request->redirect(null, 'admin');
    }

    /**
     * Download scheduled task execution log file.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function downloadScheduledTaskLogFile($args, $request)
    {
        $file = basename($request->getUserVar('file'));
        ScheduledTaskHelper::downloadExecutionLog($file);
    }

    /**
     * Clear scheduled tasks execution logs.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function clearScheduledTaskLogFiles($args, $request)
    {
        if (!$request->checkCSRF()) {
            return new JSONMessage(false);
        }

        ScheduledTaskHelper::clearExecutionLogs();

        $request->redirect(null, 'admin');
    }

    /**
     * List the jobs waiting to be executed in the queue
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function jobs($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'jobs',
            'name' => __('navigation.tools.jobs'),
        ];

        $templateMgr->setState(['pageInitConfig' => $this->getJobsTableState($request)]);

        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => 'navigation.tools.jobs',
        ]);

        $templateMgr->display('admin/jobs.tpl');
    }

    /**
     * Build the state data for the queued jobs table
     */
    protected function getJobsTableState(PKPRequest $request): array
    {
        return [
            'i18nDescription' => __('admin.jobs.totalCount'),
            'label' => __('admin.jobs.viewQueuedJobs'),
            'columns' => [
                [
                    'name' => 'id',
                    'label' => __('admin.jobs.list.id'),
                    'value' => 'id',
                ],
                [
                    'name' => 'title',
                    'label' => __('admin.jobs.list.displayName'),
                    'value' => 'displayName',
                ],
                [
                    'name' => 'queue',
                    'label' => __('admin.jobs.list.queue'),
                    'value' => 'queue',
                ],
                [
                    'name' => 'attempts',
                    'label' => __('admin.jobs.list.attempts'),
                    'value' => 'attempts',
                ],
                [
                    'name' => 'created_at',
                    'label' => __('admin.jobs.list.createdAt'),
                    'value' => 'created_at',
                ]
            ],
            'apiUrl' => $request->getDispatcher()->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'jobs/all'),
        ];
    }

    /**
     * List the queue jobs failied to execute
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function failedJobs($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'failedJobs',
            'name' => __('navigation.tools.jobs.failed'),
        ];

        $templateMgr->setState(['pageInitConfig' => $this->getFailedJobsTableState($request)]);

        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => 'navigation.tools.jobs.failed',
        ]);

        $templateMgr->display('admin/failedJobs.tpl');
    }

    /**
     * Build the state data for the queued jobs table
     */
    protected function getFailedJobsTableState(PKPRequest $request): array
    {
        return [
            'i18nDescription' => __('admin.jobs.failed.totalCount'),
            'label' => __('navigation.tools.jobs.failed.view'),
            'columns' => [
                [
                    'name' => 'id',
                    'label' => __('admin.jobs.list.id'),
                    'value' => 'id',
                ],
                [
                    'name' => 'title',
                    'label' => __('admin.jobs.list.displayName'),
                    'value' => 'displayName',
                ],
                [
                    'name' => 'queue',
                    'label' => __('admin.jobs.list.queue'),
                    'value' => 'queue',
                ],
                [
                    'name' => 'connection',
                    'label' => __('admin.jobs.list.connection'),
                    'value' => 'connection',
                ],
                [
                    'name' => 'failed_at',
                    'label' => __('admin.jobs.list.failedAt'),
                    'value' => 'failed_at',
                ],
                [
                    'name' => 'actions',
                    'label' => __('admin.jobs.list.actions'),
                    'value' => 'action',
                ],
            ],
            'apiUrl' => $request->getDispatcher()->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'jobs/failed/all'),
            'apiUrlRedispatchAll' => $request->getDispatcher()->url($request, Application::ROUTE_API, Application::SITE_CONTEXT_PATH, 'jobs/redispatch/all'),
        ];
    }

    /**
     * Show the failed jobs details
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function failedJobDetails($args, $request)
    {
        $this->setupTemplate($request);

        $templateMgr = TemplateManager::getManager($request);

        $failedJob = Repo::failedJob()->get((int) $args[0]);

        if (!$failedJob) {
            $request->getDispatcher()->handle404();
        }

        $rows = collect(array_merge(HttpFailedJobResource::toResourceArray($failedJob), [
            'payload' => $failedJob->first()->getRawOriginal('payload'),
        ]))
            ->map(fn ($value, $attribute) => is_array($value) ? null : [
                'attribute' => '<b>' . __('admin.jobs.list.' . Str::of($attribute)->snake()->replace('_', ' ')->camel()->value()) . '</b>',
                'value' => isValidJson($value) ? json_encode(json_decode($value, true), JSON_PRETTY_PRINT) : $value
            ])
            ->filter()
            ->values();

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'FailedJobDetailsPage',
            'name' => __('navigation.tools.jobs.failed.details'),
        ];

        $templateMgr->setState(
            [
                'pageInitConfig' => [
                    'label' => __('navigation.tools.job.failed.details.view', ['id' => $failedJob->id]),
                    'columns' => [
                        [
                            'name' => 'attribute',
                            'label' => __('admin.job.failed.list.attribute'),
                            'value' => 'attribute',
                        ],
                        [
                            'name' => 'value',
                            'label' => __('admin.job.failed.list.attribute.value'),
                            'value' => 'value',
                        ],
                    ],
                    'rows' => $rows,
                ]
            ]
        );

        $templateMgr->assign([
            'pageComponent' => 'Page',
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => 'navigation.tools.jobs.failed.details',
        ]);

        $templateMgr->display('admin/failedJobDetails.tpl');
    }

    /**
     * Get the highlights list panel
     */
    protected function getHighlightsListPanel(): HighlightsListPanel
    {
        $request = Application::get()->getRequest();
        $dispatcher = $request->getDispatcher();
        $apiUrl = $dispatcher->url(
            $request,
            Application::ROUTE_API,
            Application::SITE_CONTEXT_PATH,
            'highlights'
        );

        $highlightForm = new HighlightForm(
            $apiUrl,
            Repo::highlight()->getFileUploadBaseUrl(),
            $dispatcher->url(
                Application::get()->getRequest(),
                Application::ROUTE_API,
                Application::SITE_CONTEXT_PATH,
                'temporaryFiles'
            )
        );

        $items = Repo::highlight()
            ->getCollector()
            ->withSiteHighlights(HighlightCollector::SITE_ONLY)
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

    /*
     * Get the list panel for site-wide announcements
     */
    protected function getAnnouncementsListPanel(string $apiUrl, PKPAnnouncementForm $form): PKPAnnouncementsListPanel
    {
        $announcements = Announcement::withContextIds([PKPApplication::SITE_CONTEXT_ID]);

        $itemsMax = $announcements->count();
        $items = Repo::announcement()->getSchemaMap()->summarizeMany(
            $announcements->limit(30)->get()
        );

        return new PKPAnnouncementsListPanel(
            'announcements',
            __('manager.setup.announcements'),
            [
                'apiUrl' => $apiUrl,
                'form' => $form,
                'getParams' => [
                    'contextIds' => [PKPApplication::SITE_CONTEXT_ID],
                    'count' => 30,
                ],
                'items' => $items->values(),
                'itemsMax' => $itemsMax,
            ]
        );
    }
}
