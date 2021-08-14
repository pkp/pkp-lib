<?php

/**
 * @file pages/stats/PKPStatsHandler.inc.php
 *
 * Copyright (c) 2013-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPStatsHandler
 * @ingroup pages_stats
 *
 * @brief Handle requests for statistics pages.
 */

use APP\core\Request;
use APP\facades\Repo;

use APP\handler\Handler;
use APP\statistics\StatisticsHelper;

use APP\template\TemplateManager;
use PKP\security\authorization\ContextAccessPolicy;
use PKP\security\Role;
use PKP\statistics\PKPStatisticsHelper;

class PKPStatsHandler extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR],
            ['editorial', 'publications', 'users', 'reports']
        );
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


    //
    // Public handler methods.
    //
    /**
     * Display editorial stats about the submission workflow process
     *
     * @param array $args
     * @param Request $request
     */
    public function editorial($args, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $dateStart = date('Y-m-d', strtotime('-91 days'));
        $dateEnd = date('Y-m-d', strtotime('yesterday'));

        $args = [
            'contextIds' => [$context->getId()],
        ];

        $totals = Services::get('editorialStats')->getOverview($args);
        $averages = Services::get('editorialStats')->getAverages($args);
        $dateRangeTotals = Services::get('editorialStats')->getOverview(
            array_merge(
                $args,
                [
                    'dateStart' => $dateStart,
                    'dateEnd' => $dateEnd,
                ]
            )
        );

        // Stats that should be converted to percentages
        $percentageStats = [
            'acceptanceRate',
            'declineRate',
            'declinedDeskRate',
            'declinedReviewRate',
        ];

        // Stats that should be indented in the table
        $indentStats = [
            'submissionsDeclinedDeskReject',
            'submissionsDeclinedPostReview',
            'daysToAccept',
            'daysToReject',
            'declinedDeskRate',
            'declinedReviewRate',
        ];

        // Compile table rows
        $tableRows = [];
        foreach ($totals as $i => $stat) {
            $row = [
                'key' => $stat['key'],
                'name' => __($stat['name']),
                'total' => $stat['value'],
                'dateRange' => $dateRangeTotals[$i]['value'],
            ];
            if (in_array($stat['key'], $indentStats)) {
                $row['name'] = ' ' . $row['name'];
            }
            if (in_array($stat['key'], $percentageStats)) {
                $row['total'] = ($stat['value'] * 100) . '%';
                $row['dateRange'] = ($dateRangeTotals[$i]['value'] * 100) . '%';
            }
            $description = $this->_getStatDescription($stat['key']);
            if ($description) {
                $row['description'] = $description;
            }
            if (array_key_exists($stat['key'], $averages)
                    && $averages[$stat['key']] !== -1
                    && $row['total'] > 0) {
                $row['total'] = __('stats.countWithYearlyAverage', [
                    'count' => $stat['value'],
                    'average' => $averages[$stat['key']],
                ]);
            }
            $tableRows[] = $row;
        }

        // Get the worflow stage counts
        $activeByStage = [];
        foreach (Application::get()->getApplicationStages() as $stageId) {
            $activeByStage[] = [
                'name' => __(Application::get()->getWorkflowStageName($stageId)),
                'count' => Services::get('editorialStats')->countActiveByStages($stageId, $args),
                'color' => Application::get()->getWorkflowStageColor($stageId),
            ];
        }

        $statsComponent = new \PKP\components\PKPStatsEditorialPage(
            $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'stats/editorial'),
            [
                'activeByStage' => $activeByStage,
                'averagesApiUrl' => $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'stats/editorial/averages'),
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'dateRangeOptions' => [
                    [
                        'dateStart' => date('Y-m-d', strtotime('-91 days')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last90Days'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime(date('Y') . '-01-01')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.thisYear'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime((date('Y') - 1) . '-01-01')),
                        'dateEnd' => date('Y-m-d', strtotime((date('Y') - 1) . '-12-31')),
                        'label' => __('stats.dateRange.lastYear'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime((date('Y') - 2) . '-01-01')),
                        'dateEnd' => date('Y-m-d', strtotime((date('Y') - 1) . '-12-31')),
                        'label' => __('stats.dateRange.lastTwoYears'),
                    ],
                ],
                'percentageStats' => $percentageStats,
                'tableColumns' => [
                    [
                        'name' => 'name',
                        'label' => __('common.name'),
                        'value' => 'name',
                    ],
                    [
                        'name' => 'dateRange',
                        'label' => $dateStart . ' — ' . $dateEnd,
                        'value' => 'dateRange',
                    ],
                    [
                        'name' => 'total',
                        'label' => __('stats.total'),
                        'value' => 'total',
                    ],
                ],
                'tableRows' => $tableRows,
            ]
        );

        $templateMgr->setLocaleKeys([
            'stats.descriptionForStat',
            'stats.countWithYearlyAverage',
        ]);
        $templateMgr->setState($statsComponent->getConfig());
        $templateMgr->assign([
            'pageComponent' => 'StatsEditorialPage',
            'pageTitle' => __('stats.editorialActivity'),
        ]);

        $templateMgr->display('stats/editorial.tpl');
    }

    /**
     * Display published submissions statistics page
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function publications($args, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $dateStart = date('Y-m-d', strtotime('-31 days'));
        $dateEnd = date('Y-m-d', strtotime('yesterday'));
        $count = 30;

        $timeline = Services::get('stats')->getTimeline(PKPStatisticsHelper::STATISTICS_DIMENSION_DAY, [
            'assocTypes' => ASSOC_TYPE_SUBMISSION,
            'contextIds' => $context->getId(),
            'count' => $count,
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
        ]);

        $statsComponent = new \PKP\components\PKPStatsPublicationPage(
            $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'stats/publications'),
            [
                'timeline' => $timeline,
                'timelineInterval' => PKPStatisticsHelper::STATISTICS_DIMENSION_DAY,
                'timelineType' => 'abstract',
                'tableColumns' => [
                    [
                        'name' => 'title',
                        'label' => __('common.title'),
                    ],
                    [
                        'name' => 'abstractViews',
                        'label' => __('submission.abstractViews'),
                        'value' => 'abstractViews',
                    ],
                    [
                        'name' => 'galleyViews',
                        'label' => __('stats.fileViews'),
                        'value' => 'galleyViews',
                    ],
                    [
                        'name' => 'pdf',
                        'label' => __('stats.pdf'),
                        'value' => 'pdfViews',
                    ],
                    [
                        'name' => 'html',
                        'label' => __('stats.html'),
                        'value' => 'htmlViews',
                    ],
                    [
                        'name' => 'other',
                        'label' => __('common.other'),
                        'value' => 'otherViews',
                    ],
                    [
                        'name' => 'total',
                        'label' => __('stats.total'),
                        'value' => 'total',
                        'orderBy' => 'total',
                        'initialOrderDirection' => true,
                    ],
                ],
                'count' => $count,
                'dateStart' => $dateStart,
                'dateEnd' => $dateEnd,
                'dateRangeOptions' => [
                    [
                        'dateStart' => $dateStart,
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last30Days'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime('-91 days')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last90Days'),
                    ],
                    [
                        'dateStart' => date('Y-m-d', strtotime('-12 months')),
                        'dateEnd' => $dateEnd,
                        'label' => __('stats.dateRange.last12Months'),
                    ],
                    [
                        'dateStart' => '',
                        'dateEnd' => '',
                        'label' => __('stats.dateRange.allDates'),
                    ],
                ],
                'orderBy' => 'total',
                'orderDirection' => true,
            ]
        );

        $templateMgr->setState($statsComponent->getConfig());
        $templateMgr->assign([
            'pageComponent' => 'StatsPublicationsPage',
            'pageTitle' => __('stats.publicationStats'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_WIDE,
        ]);

        $templateMgr->display('stats/publications.tpl');
    }

    /**
     * Display users stats
     *
     */
    public function users(array $args, Request $request): void
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        // The POST handler is here merely to serve a redirection URL to the Vue component
        if ($request->isPost()) {
            echo $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), 'users/report', null, null, $request->getUserVars());
            exit;
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $context = $request->getContext();
        $selfUrl = $dispatcher->url($request, PKPApplication::ROUTE_PAGE, $context->getPath(), 'stats', 'users');
        $reportForm = new PKP\components\forms\statistics\users\ReportForm($selfUrl, $context);

        $templateMgr->setState([
            'components' => [
                'usersReportForm' => $reportForm->getConfig()
            ]
        ]);
        $templateMgr->assign([
            'pageTitle' => __('stats.userStatistics'),
            'pageComponent' => 'StatsUsersPage',
            'userStats' => array_map(
                function ($item) {
                    $item['name'] = __($item['name']);
                    return $item;
                },
                Repo::user()->getRolesOverview(Repo::user()->getCollector()->filterByContextIds(['contextId' => $context->getId()]))
            ),
        ]);
        $templateMgr->display('stats/users.tpl');
    }

    /**
     * Route to other Reports operations
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function reports($args, $request)
    {
        $path = array_shift($args);
        switch ($path) {
            case '':
            case 'reports':
                $this->displayReports($args, $request);
                break;
            case 'report':
                $this->report($args, $request);
                break;
            case 'reportGenerator':
                $this->reportGenerator($args, $request);
                break;
            case 'generateReport':
                $this->generateReport($args, $request);
                break;
            default: assert(false);
        }
    }

    /**
     * Display report possibilities (report plugins)
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function displayReports($args, $request)
    {
        $dispatcher = $request->getDispatcher();
        $context = $request->getContext();

        if (!$context) {
            $dispatcher->handle404();
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $reportPlugins = PluginRegistry::loadCategory('reports');
        $templateMgr->assign('reportPlugins', $reportPlugins);

        $templateMgr->assign([
            'pageTitle' => __('manager.statistics.reports'),
        ]);
        $templateMgr->display('stats/reports.tpl');
    }

    /**
     * Delegates to plugins operations
     * related to report generation.
     *
     * @param array $args
     * @param Request $request
     */
    public function report($args, $request)
    {
        $this->setupTemplate($request);

        $pluginName = $request->getUserVar('pluginName');
        $reportPlugins = PluginRegistry::loadCategory('reports');

        if ($pluginName == '' || !isset($reportPlugins[$pluginName])) {
            $request->redirect(null, null, 'stats', 'reports');
        }

        $plugin = $reportPlugins[$pluginName];
        $plugin->display($args, $request);
    }

    /**
     * Display page to generate custom reports.
     *
     * @param array $args
     * @param Request $request
     */
    public function reportGenerator($args, $request)
    {
        $this->setupTemplate($request);
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->assign([
            'breadcrumbs' => [
                [
                    'id' => 'reports',
                    'name' => __('manager.statistics.reports'),
                    'url' => $request->getRouter()->url($request, null, 'stats', 'reports'),
                ],
                [
                    'id' => 'customReportGenerator',
                    'name' => __('manager.statistics.reports.customReportGenerator')
                ],
            ],
            'pageTitle' => __('manager.statistics.reports.customReportGenerator'),
        ]);
        $templateMgr->display('stats/reportGenerator.tpl');
    }

    /**
     * Generate statistics reports from passed
     * request arguments.
     *
     * @param array $args
     * @param PKPRequest $request
     */
    public function generateReport($args, $request)
    {
        $this->setupTemplate($request);

        $router = $request->getRouter();
        $context = $router->getContext($request);
        $statsHelper = new StatisticsHelper();

        $metricType = $request->getUserVar('metricType');
        if (is_null($metricType)) {
            $metricType = $context->getDefaultMetricType();
        }

        // Generates only one metric type report at a time.
        if (is_array($metricType)) {
            $metricType = current($metricType);
        }
        if (!is_scalar($metricType)) {
            $metricType = null;
        }

        $reportPlugin = $statsHelper->getReportPluginByMetricType($metricType);
        if (!$reportPlugin || is_null($metricType)) {
            $request->redirect(null, null, 'stats', 'reports');
        }

        $columns = $request->getUserVar('columns');
        $filters = (array) json_decode($request->getUserVar('filters'));
        if (!$filters) {
            $filters = $request->getUserVar('filters');
        }

        $orderBy = $request->getUserVar('orderBy');
        if ($orderBy) {
            $orderBy = (array) json_decode($orderBy);
            if (!$orderBy) {
                $orderBy = $request->getUserVar('orderBy');
            }
        } else {
            $orderBy = [];
        }

        $metrics = $reportPlugin->getMetrics($metricType, $columns, $filters, $orderBy);

        $allColumnNames = $statsHelper->getColumnNames();
        $columnOrder = array_keys($allColumnNames);
        $columnNames = [];

        foreach ($columnOrder as $column) {
            if (in_array($column, $columns)) {
                $columnNames[$column] = $allColumnNames[$column];
            }

            if ($column == PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE && in_array(PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_ID, $columns)) {
                $columnNames['common.title'] = __('common.title');
            }
        }

        // Make sure the metric column will always be present.
        if (!in_array(PKPStatisticsHelper::STATISTICS_METRIC, $columnNames)) {
            $columnNames[PKPStatisticsHelper::STATISTICS_METRIC] = $allColumnNames[PKPStatisticsHelper::STATISTICS_METRIC];
        }

        header('content-type: text/comma-separated-values');
        header('content-disposition: attachment; filename=statistics-' . date('Ymd') . '.csv');
        $fp = fopen('php://output', 'wt');
        //Add BOM (byte order mark) to fix UTF-8 in Excel
        fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($fp, [$reportPlugin->getDisplayName()]);
        fputcsv($fp, [$reportPlugin->getDescription()]);
        fputcsv($fp, [__('common.metric') . ': ' . $metricType]);
        fputcsv($fp, [__('manager.statistics.reports.reportUrl') . ': ' . $request->getCompleteUrl()]);
        fputcsv($fp, ['']);

        // Just for better displaying.
        $columnNames = array_merge([''], $columnNames);

        fputcsv($fp, $columnNames);
        foreach ($metrics as $record) {
            $row = [];
            foreach ($columnNames as $key => $name) {
                if (empty($name)) {
                    // Column just for better displaying.
                    $row[] = '';
                    continue;
                }

                // Give a chance for subclasses to set the row values.
                if ($returner = $this->getReportRowValue($key, $record)) {
                    $row[] = $returner;
                    continue;
                }

                switch ($key) {
                    case 'common.title':
                        $assocId = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_ID];
                        $assocType = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE];
                        $row[] = $this->getObjectTitle($assocId, $assocType);
                        break;
                    case PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE:
                        $assocType = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_ASSOC_TYPE];
                        $row[] = $statsHelper->getObjectTypeString($assocType);
                        break;
                    case PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID:
                        $assocId = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_CONTEXT_ID];
                        $assocType = Application::getContextAssocType();
                        $row[] = $this->getObjectTitle($assocId, $assocType);
                        break;
                    case PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID:
                        if (isset($record[PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID])) {
                            $assocId = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_SUBMISSION_ID];
                            $assocType = ASSOC_TYPE_SUBMISSION;
                            $row[] = $this->getObjectTitle($assocId, $assocType);
                        } else {
                            $row[] = '';
                        }
                        break;
                    case PKPStatisticsHelper::STATISTICS_DIMENSION_REGION:
                        if (isset($record[PKPStatisticsHelper::STATISTICS_DIMENSION_REGION]) && isset($record[PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY])) {
                            $geoLocationTool = $statsHelper->getGeoLocationTool();
                            if ($geoLocationTool) {
                                $regions = $geoLocationTool->getRegions($record[PKPStatisticsHelper::STATISTICS_DIMENSION_COUNTRY]);
                                $regionId = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_REGION];
                                if (strlen($regionId) == 1) {
                                    $regionId = '0' . $regionId;
                                }
                                if (isset($regions[$regionId])) {
                                    $row[] = $regions[$regionId];
                                    break;
                                }
                            }
                        }
                        $row[] = '';
                        break;
                    case PKPStatisticsHelper::STATISTICS_DIMENSION_PKP_SECTION_ID:
                        $sectionId = null;
                        if (isset($record[PKPStatisticsHelper::STATISTICS_DIMENSION_PKP_SECTION_ID])) {
                            $sectionId = $record[PKPStatisticsHelper::STATISTICS_DIMENSION_PKP_SECTION_ID];
                        }
                        if ($sectionId) {
                            $row[] = $this->getObjectTitle($sectionId, ASSOC_TYPE_SECTION);
                        } else {
                            $row[] = '';
                        }
                        break;
                    case PKPStatisticsHelper::STATISTICS_DIMENSION_FILE_TYPE:
                        if ($record[$key]) {
                            $row[] = $statsHelper->getFileTypeString($record[$key]);
                        } else {
                            $row[] = '';
                        }
                        break;
                    default:
                        $row[] = $record[$key];
                        break;
                }
            }
            fputcsv($fp, $row);
        }
        fclose($fp);
    }

    //
    // Protected methods.
    //
    /**
     * Get the row value based on the column key (usually assoc types)
     * and the current record.
     *
     * @param string|int $key
     * @param array $record
     *
     * @return string
     */
    protected function getReportRowValue($key, $record)
    {
        return null;
    }

    /**
     * Get data object title based on passed
     * assoc type and id.
     *
     * @param int $assocId
     * @param int $assocType
     *
     * @return string
     */
    protected function getObjectTitle($assocId, $assocType)
    {
        switch ($assocType) {
            case Application::getContextAssocType():
                $contextDao = Application::getContextDAO(); /** @var ContextDAO $contextDao */
                $context = $contextDao->getById($assocId);
                if (!$context) {
                    break;
                }
                return $context->getLocalizedName();
            case ASSOC_TYPE_SUBMISSION:
                $submission = Repo::submission()->get($assocId, null, true);
                if (!$submission) {
                    break;
                }
                return $submission->getLocalizedTitle();
            case ASSOC_TYPE_SECTION:
                $sectionDao = Application::getSectionDAO();
                $section = $sectionDao->getById($assocId);
                if (!$section) {
                    break;
                }
                return $section->getLocalizedTitle();
            case ASSOC_TYPE_SUBMISSION_FILE:
                $submissionFile = Repo::submissionFile()->get($assocId);
                if (!$submissionFile) {
                    break;
                }
                return $submissionFile->getLocalizedData('name');
        }

        return __('manager.statistics.reports.objectNotFound');
    }

    /**
     * Get a description for stats that require one
     *
     * @param string $key
     */
    protected function _getStatDescription($key)
    {
        switch ($key) {
            case 'daysToDecision': return __('stats.description.daysToDecision');
            case 'acceptanceRate': return __('stats.description.acceptRejectRate');
            case 'declineRate': return __('stats.description.acceptRejectRate');
        }
        return '';
    }
}
