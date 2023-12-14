<?php
/**
 * @file pages/dashboard/DashboardHandlerNext.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DashboardHandlerNext
 *
 * @ingroup pages_dashboard
 *
 * @brief Handle requests for user's dashboard.
 */

namespace PKP\pages\dashboard;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\components\forms\dashboard\SubmissionFilters;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\submission\DashboardView;
use PKP\submission\GenreDAO;
use PKP\submission\PKPSubmission;

define('SUBMISSIONS_LIST_ACTIVE', 'active');
define('SUBMISSIONS_LIST_ARCHIVE', 'archive');
define('SUBMISSIONS_LIST_MY_QUEUE', 'myQueue');
define('SUBMISSIONS_LIST_UNASSIGNED', 'unassigned');

class DashboardHandlerNext extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    public int $perPage = 30;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();

        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_AUTHOR, Role::ROLE_ID_REVIEWER, Role::ROLE_ID_ASSISTANT],
            ['index', 'tasks', 'myQueue', 'unassigned', 'active', 'archives']
        );
    }

    /**
     * @copydoc PKPHandler::authorize()
     */
    public function authorize($request, &$args, $roleAssignments)
    {
        $this->addPolicy(new PKPSiteAccessPolicy($request, null, $roleAssignments));
        return parent::authorize($request, $args, $roleAssignments);
    }

    /**
     * Display about index page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function index($args, $request)
    {
        $context = $request->getContext();
        $dispatcher = $request->getDispatcher();

        if (!$context) {
            $request->redirect(null, 'user');
        }

        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_submissions');

        $sections = Repo::section()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $categories = Repo::category()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        $filtersForm = new SubmissionFilters(
            $context,
            $userRoles,
            $sections,
            $categories
        );

        $collector = Repo::submission()
            ->getCollector()
            ->filterByContextIds([(int) $request->getContext()->getId()])
            ->filterByStatus([PKPSubmission::STATUS_QUEUED]);

        if (empty(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            $collector->assignedTo([(int) $request->getUser()->getId()]);
        }

        $userGroups = Repo::userGroup()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->getMany();

        /** @var GenreDAO $genreDao */
        $genreDao = DAORegistry::getDAO('GenreDAO');
        $genres = $genreDao->getByContextId($context->getId())->toArray();

        $templateMgr->setState([
            'pageInitConfig' => [
                'apiUrl' => $apiUrl,
                'assignParticipantUrl' => $dispatcher->url(
                    $request,
                    Application::ROUTE_COMPONENT,
                    null,
                    'grid.users.stageParticipant.StageParticipantGridHandler',
                    'addParticipant',
                    null,
                    [
                        'submissionId' => '__id__',
                        'stageId' => '__stageId__',
                    ]
                ),
                'countPerPage' => $this->perPage,
                'currentViewId' => 'active',
                'filtersForm' => $filtersForm->getConfig(),
                'submissions' => Repo::submission()
                    ->getSchemaMap()
                    ->mapManyToSubmissionsList(
                        $collector->limit($this->perPage)->getMany(),
                        $userGroups,
                        $genres
                    )
                    ->values(),
                'submissionsCount' => $collector->limit(null)->getCount(),
                'views' => $this->getViews(),
                'columns' => $this->getColumns(),

            ]
        ]);

        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
            'pageTitle' => __('navigation.submissions'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);

        $templateMgr->setConstants([
            'STAGE_STATUS_SUBMISSION_UNASSIGNED' => Repo::submission()::STAGE_STATUS_SUBMISSION_UNASSIGNED,
        ]);

        $templateMgr->display('dashboard/editors.tpl');
    }

    /**
     * View tasks popup
     *
     * @param array $args
     * @param PKPRequest $request
     *
     * @return JSONMessage JSON object
     */
    public function tasks($args, $request)
    {
        $templateMgr = TemplateManager::getManager($request);
        $this->setupTemplate($request);

        return $templateMgr->fetchJson('dashboard/tasks.tpl');
    }

    /**
     * Get a list of the pre-configured views
     *
     * @hook Dashboard::views [[&$views, $userRoles]]
     */
    protected function getViews(): array
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();
        $user = $request->getUser();
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);
        $dashboardViews = Repo::submission()->getDashboardViews($context, $user);
        $viewsData = $dashboardViews->map(fn (DashboardView $dashboardView) => $dashboardView->getData())->values()->toArray();

        Hook::call('Dashboard::views', [&$viewsData, $userRoles]);

        return $viewsData;
    }

    /**
     * Define the columns in the submissions table
     *
     * @hook Dashboard::columns [[&$columns, $userRoles]]
     */
    protected function getColumns(): array
    {
        $columns = [
            $this->createColumn('id', __('common.id'), 'columnId', true),
            $this->createColumn('title', __('navigation.submissions'), 'columnTitle'),
            $this->createColumn('stage', __('workflow.stage'), 'columnStage'),
            $this->createColumn('days', __('editor.submission.days'), 'columnDays'),
            $this->createColumn('activity', __('stats.editorialActivity'), 'columnActivity'),
            $this->createColumn('actions', __('admin.jobs.list.actions'), 'columnActions')
        ];

        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        Hook::call('Dashboard::columns', [&$columns, $userRoles]);

        return $columns;
    }

    /**
     * Creates a new table column
     */
    protected function createColumn(string $id, string $header, string $componentName, bool $sortable = false): object
    {
        return new class ($id, $header, $componentName, $sortable) {
            public function __construct(
                public string $id,
                public string $header,
                public string $componentName,
                public bool $sortable,
            ) {
            }
        };
    }
}
