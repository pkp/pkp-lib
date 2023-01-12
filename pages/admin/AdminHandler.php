<?php

/**
 * @file pages/admin/AdminHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class AdminHandler
 * @ingroup pages_admin
 *
 * @brief Handle requests for site administration functions.
 */

namespace PKP\pages\admin;

use APP\core\Application;
use APP\core\Services;
use APP\facades\Repo;
use APP\file\PublicFileManager;
use APP\handler\Handler;
use APP\template\TemplateManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use PKP\cache\CacheManager;
use PKP\config\Config;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\job\resources\HttpFailedJobResource;
use PKP\scheduledTask\ScheduledTaskHelper;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\site\VersionCheck;

class AdminHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    public const JOBS_PER_PAGE = 10;

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
                        'url' => $router->url($request, 'index', 'admin'),
                        'name' => __('navigation.admin'),
                    ]
                ]
            ]);
        }

        // Interact with the beacon (if enabled) and determine if a new version exists
        $latestVersion = VersionCheck::checkIfNewVersionExists();

        // Display a warning message if there is a new version of OJS available
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

        $apiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::CONTEXT_ID_ALL, 'site');
        $themeApiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::CONTEXT_ID_ALL, 'site/theme');
        $temporaryFileApiUrl = $dispatcher->url($request, Application::ROUTE_API, Application::CONTEXT_ID_ALL, 'temporaryFiles');

        $publicFileManager = new PublicFileManager();
        $baseUrl = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath();

        $locales = $site->getSupportedLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $contexts = Services::get('context')->getManySummary();

        $siteAppearanceForm = new \PKP\components\forms\site\PKPSiteAppearanceForm($apiUrl, $locales, $site, $baseUrl, $temporaryFileApiUrl);
        $siteConfigForm = new \PKP\components\forms\site\PKPSiteConfigForm($apiUrl, $locales, $site);
        $siteInformationForm = new \PKP\components\forms\site\PKPSiteInformationForm($apiUrl, $locales, $site);
        $siteBulkEmailsForm = new \PKP\components\forms\site\PKPSiteBulkEmailsForm($apiUrl, $site, $contexts);
        $themeForm = new \PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales);
        $siteStatisticsForm = new \PKP\components\forms\site\PKPSiteStatisticsForm($apiUrl, $locales, $site);

        $templateMgr = TemplateManager::getManager($request);

        $templateMgr->setState([
            'components' => [
                FORM_SITE_APPEARANCE => $siteAppearanceForm->getConfig(),
                FORM_SITE_CONFIG => $siteConfigForm->getConfig(),
                FORM_SITE_INFO => $siteInformationForm->getConfig(),
                FORM_SITE_BULK_EMAILS => $siteBulkEmailsForm->getConfig(),
                FORM_THEME => $themeForm->getConfig(),
                FORM_SITE_STATISTICS => $siteStatisticsForm->getConfig(),
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
            'componentAvailability' => $this->siteSettingsAvailability($request),
        ]);

        $templateMgr->display('admin/settings.tpl');
    }

    /**
     * Business logic for site settings single/multiple contexts availability
     *
     * @param PKPRequest $request
     *
     * @return array [siteComponent, availability (bool)]
     */
    private function siteSettingsAvailability($request)
    {
        $tabsSingleContextAvailability = [
            'siteSetup',
            'languages',
            'bulkEmails',
            'statistics',
        ];

        $tabs = [
            'siteSetup',
            'siteAppearance',
            'sitePlugins',
            'siteConfig',
            'siteInfo',
            'languages',
            'navigationMenus',
            'bulkEmails',
            'siteTheme',
            'siteAppearanceSetup',
            'statistics',
        ];

        $singleContextSite = (Services::get('context')->getCount() == 1);

        $tabsAvailability = [];

        foreach ($tabs as $tab) {
            $tabsAvailability[$tab] = true;
            if ($singleContextSite && !in_array($tab, $tabsSingleContextAvailability)) {
                $tabsAvailability[$tab] = false;
            }
        }

        return $tabsAvailability;
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

        $contextService = Services::get('context');
        $context = $contextService->get((int) $args[0]);

        if (empty($context)) {
            $request->getDispatcher()->handle404();
        }

        $apiUrl = $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId());
        $themeApiUrl = $dispatcher->url($request, Application::ROUTE_API, $context->getPath(), 'contexts/' . $context->getId() . '/theme');
        $sitemapUrl = $router->url($request, $context->getPath(), 'sitemap');

        $locales = $context->getSupportedFormLocaleNames();
        $locales = array_map(fn (string $locale, string $name) => ['key' => $locale, 'label' => $name], array_keys($locales), $locales);

        $contextForm = new \APP\components\forms\context\ContextForm($apiUrl, $locales, $request->getBaseUrl(), $context);
        $themeForm = new \PKP\components\forms\context\PKPThemeForm($themeApiUrl, $locales, $context);
        $indexingForm = new \PKP\components\forms\context\PKPSearchIndexingForm($apiUrl, $locales, $context, $sitemapUrl);

        $components = [
            FORM_CONTEXT => $contextForm->getConfig(),
            FORM_SEARCH_INDEXING => $indexingForm->getConfig(),
            FORM_THEME => $themeForm->getConfig(),
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
            'url' => $router->url($request, 'index', 'admin', 'contexts'),
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
        $this->setupTemplate($request, true);

        $versionDao = DAORegistry::getDAO('VersionDAO'); /** @var VersionDAO $versionDao */
        $currentVersion = $versionDao->getCurrentVersion();

        if ($request->getUserVar('versionCheck')) {
            $latestVersionInfo = VersionCheck::getLatestVersion();
            $latestVersionInfo['patch'] = VersionCheck::getPatch($latestVersionInfo);
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

        $sessionDao = DAORegistry::getDAO('SessionDAO'); /** @var SessionDAO $sessionDao */
        $sessionDao->deleteAllSessions();
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

        // Clear the CacheManager's caches
        $cacheManager = CacheManager::getManager();
        $cacheManager->flush();

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
        $this->setupTemplate($request, true);

        $templateMgr = TemplateManager::getManager($request);

        $page = (int) ($request->getUserVar('page') ?? 1);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'jobs',
            'name' => __('navigation.tools.jobs'),
        ];

        $templateMgr->setState($this->getJobsTableState($page));

        $templateMgr->assign([
            'pageComponent' => 'JobsPage',
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('navigation.tools.jobs'),
        ]);

        $templateMgr->display('admin/jobs.tpl');
    }

    /**
     * Build the state data for the queued jobs table
     */
    protected function getJobsTableState(int $page = 1): array
    {
        $total = Repo::job()->total();

        $queuedJobsItems = Repo::job()
            ->setOutputFormat(Repo::job()::OUTPUT_HTTP)
            ->perPage(self::JOBS_PER_PAGE)
            ->setPage($page)
            ->showJobs();

        return [
            'label' => __('admin.jobs.viewQueuedJobs'),
            'description' => __('admin.jobs.totalCount', ['total' => $total]),
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
            'rows' => $queuedJobsItems->all(),
            'total' => $total,
            'lastPage' => $queuedJobsItems->lastPage(),
            'currentPage' => $queuedJobsItems->currentPage(),
            'isLoadingItems' => false,
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
        $this->setupTemplate($request, true);

        $templateMgr = TemplateManager::getManager($request);

        $page = (int) ($request->getUserVar('page') ?? 1);

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'failedJobs',
            'name' => __('navigation.tools.jobs.failed'),
        ];

        $templateMgr->setState($this->getFailedJobsTableState($request, $page));

        $templateMgr->assign([
            'pageComponent' => 'FailedJobsPage',
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('navigation.tools.jobs.failed'),
        ]);

        $templateMgr->display('admin/failedJobs.tpl');
    }

    /**
     * Build the state data for the queued jobs table
     */
    protected function getFailedJobsTableState(PKPRequest $request, int $page = 1): array
    {
        $total = Repo::failedJob()->total();

        $failedJobs = Repo::failedJob()
            ->setOutputFormat(Repo::failedJob()::OUTPUT_HTTP)
            ->perPage(self::JOBS_PER_PAGE)
            ->setPage($page)
            ->showJobs();

        return [
            'label' => __('navigation.tools.jobs.failed.view'),
            'description' => __('admin.jobs.failed.totalCount', ['total' => $total]),
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
            'rows' => collect($failedJobs->all())->map(fn($failedJob) => array_merge($failedJob, [
                'detailsPath' => $request->getDispatcher()->url($request, Application::ROUTE_PAGE, 'index', 'admin', 'failedJobDetails', $failedJob['id'])
            ]))->toArray(),
            'total' => $total,
            'lastPage' => $failedJobs->lastPage(),
            'currentPage' => $failedJobs->currentPage(),
            'isLoadingItems' => false,
            'apiUrl' => $request->getDispatcher()->url($request, Application::ROUTE_API, 'admin', 'jobs'),
        ];
    }

    /**
     * 
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function failedJobDetails($args, $request)
    {
        $this->setupTemplate($request, true);

        $templateMgr = TemplateManager::getManager($request);
        
        $failedJob = Repo::failedJob()->newQuery()->find([(int) $args[0]]);

        if (!$failedJob->first()) {
            $request->getDispatcher()->handle404();
        }
        
        $rows = collect(array_merge(HttpFailedJobResource::collection($failedJob)->first(), [
                'payload' => $failedJob->first()->getRawOriginal('payload'),
            ]))
            ->map(fn($value, $attribute) => [
                'attribute' => '<b>' . __("admin.jobs.list." . Str::of($attribute)->snake()->replace('_', ' ')->camel()->value()) . '</b>',
                'value' => isValidJson($value) ? json_encode(json_decode($value, true), JSON_PRETTY_PRINT): $value
            ])
            ->values();

        $breadcrumbs = $templateMgr->getTemplateVars('breadcrumbs');
        $breadcrumbs[] = [
            'id' => 'FailedJobDetailsPage',
            'name' => __('navigation.tools.jobs.failed.details'),
        ];

        $templateMgr->setState([
            'label' => __('navigation.tools.job.failed.details.view', ['id' => $failedJob->first()->id]),
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
        ]);

        $templateMgr->assign([
            'pageComponent' => 'FailedJobDetailsPage',
            'breadcrumbs' => $breadcrumbs,
            'pageTitle' => __('navigation.tools.jobs.failed.details'),
        ]);

        $templateMgr->display('admin/failedJobDetails.tpl');
    }
    
}
