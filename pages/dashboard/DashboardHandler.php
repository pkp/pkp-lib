<?php
/**
 * @file pages/dashboard/DashboardHandler.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DashboardHandler
 *
 * @ingroup pages_dashboard
 *
 * @brief Handle requests for user's dashboard.
 */

namespace PKP\pages\dashboard;

use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\components\forms\dashboard\SubmissionFilters;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\submission\PKPSubmission;

define('SUBMISSIONS_LIST_ACTIVE', 'active');
define('SUBMISSIONS_LIST_ARCHIVE', 'archive');
define('SUBMISSIONS_LIST_MY_QUEUE', 'myQueue');
define('SUBMISSIONS_LIST_UNASSIGNED', 'unassigned');

class DashboardHandler extends Handler
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
            'apiUrl' => $apiUrl,
            'count' => $this->perPage,
            'currentViewId' => 'active',
            'filtersForm' => $filtersForm->getConfig(),
            'i18nReviewRound' => __('common.reviewRoundNumber'),
            'i18nShowingXofX' => __('common.showingXofX'),
            'submissions' => Repo::submission()
                ->getSchemaMap()
                ->mapManyToSubmissionsList(
                    $collector->limit($this->perPage)->getMany(),
                    $userGroups,
                    $genres
                )
                ->values(),
            'submissionsMax' => $collector->limit(null)->getCount(),
            'views' => $this->getViews(),
        ]);

        $templateMgr->assign([
            'pageComponent' => 'SubmissionsPage',
            'pageTitle' => __('navigation.submissions'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);

        return $templateMgr->display('dashboard/index.tpl');
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
     */
    protected function getViews(): array
    {
        $user = Application::get()->getRequest()->getUser();
        $userRoles = $this->getAuthorizedContextObject(Application::ASSOC_TYPE_USER_ROLES);

        $views = [
            [
                'id' => 'assigned-to-me',
                'name' => 'Assigned to me',
                'count' => 11,
                'queryParams' => [
                    'assignedTo' => [$user->getId()],
                    'status' => [Submission::STATUS_QUEUED],
                ]
            ],
            [
                'id' => 'active',
                'name' => 'Active Submissions',
                'count' => 83,
                'queryParams' => [
                    'status' => [Submission::STATUS_QUEUED],
                ]
            ],
            [
                'id' => 'initial-review',
                'name' => 'All in desk/initial review',
                'count' => 34,
                'queryParams' => [
                    'stageIds' => [WORKFLOW_STAGE_ID_SUBMISSION],
                    'status' => [Submission::STATUS_QUEUED],
                ]
            ],
            [
                'id' => 'external-review',
                'name' => 'All in peer review',
                'count' => 18,
                'queryParams' => [
                    'stageIds' => [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW],
                    'status' => [Submission::STATUS_QUEUED],
                ]
            ],
            [
                'id' => 'copyediting',
                'name' => 'All in copyediting',
                'count' => 4,
                'queryParams' => [
                    'stageIds' => [WORKFLOW_STAGE_ID_EDITING],
                    'status' => [Submission::STATUS_QUEUED],
                ]
            ],
            [
                'id' => 'production',
                'name' => 'All in production',
                'count' => 6,
                'queryParams' => [
                    'stageIds' => [WORKFLOW_STAGE_ID_PRODUCTION],
                    'status' => [Submission::STATUS_QUEUED],
                ]
            ],
            [
                'id' => 'scheduled',
                'name' => 'Scheduled for publication',
                'count' => 3,
                'queryParams' => [
                    'status' => [Submission::STATUS_SCHEDULED],
                ]
            ],
            [
                'id' => 'published',
                'name' => 'Published',
                'count' => 126,
                'queryParams' => [
                    'status' => [Submission::STATUS_PUBLISHED],
                ]
            ],
            [
                'id' => 'declined',
                'name' => 'Declined',
                'count' => 6921,
                'queryParams' => [
                    'status' => [Submission::STATUS_DECLINED],
                ]
            ],
        ];

        Hook::call('Dashboard::views', [&$views, $userRoles]);

        return $views;
    }
}
