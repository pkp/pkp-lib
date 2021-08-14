<?php
/**
 * @file pages/dashboard/DashboardHandler.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DashboardHandler
 * @ingroup pages_dashboard
 *
 * @brief Handle requests for user's dashboard.
 */

use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
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

        $currentUser = $request->getUser();
        $userRoles = $this->getAuthorizedContextObject(ASSOC_TYPE_USER_ROLES);
        $apiUrl = $dispatcher->url($request, PKPApplication::ROUTE_API, $context->getPath(), '_submissions');
        $lists = [];

        $includeIssuesFilter = array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $userRoles);
        $includeAssignedEditorsFilter = array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles);
        $includeCategoriesFilter = array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT], $userRoles);

        // Get all available categories
        $categories = [];
        $categoryCollection = Repo::category()->getMany(
            Repo::category()->getCollector()
                ->filterByContextIds([$context->getId()])
        );
        foreach ($categoryCollection as $category) {
            $categories[] = [
                'id' => $category->getId(),
                'title' => $category->getLocalizedTitle(),
            ];
        }

        // My Queue
        $collector = Repo::submission()->getCollector()
            ->filterByContextIds([(int) $request->getContext()->getId()])
            ->filterByStatus([PKPSubmission::STATUS_QUEUED])
            ->assignedTo([(int) $request->getUser()->getId()]);

        $itemsMax = Repo::submission()->getCount($collector);
        $items = Repo::submission()->getMany($collector->limit(30));

        $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
        $userGroups = $userGroupDao->getByContextId($context->getId())->toArray();

        $items = Repo::submission()->getSchemaMap()->mapManyToSubmissionsList($items, $userGroups);

        $myQueueListPanel = new \APP\components\listPanels\SubmissionsListPanel(
            SUBMISSIONS_LIST_MY_QUEUE,
            __('common.queue.long.myAssigned'),
            [
                'apiUrl' => $apiUrl,
                'getParams' => [
                    'status' => PKPSubmission::STATUS_QUEUED,
                    'assignedTo' => [(int) $request->getUser()->getId()],
                ],
                'includeIssuesFilter' => $includeIssuesFilter,
                'includeCategoriesFilter' => $includeCategoriesFilter,
                'includeActiveSectionFiltersOnly' => true,
                'items' => $items,
                'itemsMax' => $itemsMax,
                'categories' => $categories,
            ]
        );
        $lists[$myQueueListPanel->id] = $myQueueListPanel->getConfig();

        if (!empty(array_intersect([Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER], $userRoles))) {

            // Unassigned
            $unassignedListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                SUBMISSIONS_LIST_UNASSIGNED,
                __('common.queue.long.submissionsUnassigned'),
                [
                    'apiUrl' => $apiUrl,
                    'getParams' => [
                        'status' => PKPSubmission::STATUS_QUEUED,
                        'assignedTo' => \PKP\submission\Collector::UNASSIGNED,
                    ],
                    'lazyLoad' => true,
                    'includeIssuesFilter' => $includeIssuesFilter,
                    'includeCategoriesFilter' => $includeCategoriesFilter,
                    'includeActiveSectionFiltersOnly' => true,
                    'categories' => $categories,
                ]
            );
            $lists[$unassignedListPanel->id] = $unassignedListPanel->getConfig();

            // Active
            $activeListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                SUBMISSIONS_LIST_ACTIVE,
                __('common.queue.long.active'),
                [
                    'apiUrl' => $apiUrl,
                    'getParams' => [
                        'status' => PKPSubmission::STATUS_QUEUED,
                    ],
                    'lazyLoad' => true,
                    'includeIssuesFilter' => $includeIssuesFilter,
                    'includeCategoriesFilter' => $includeCategoriesFilter,
                    'includeAssignedEditorsFilter' => $includeAssignedEditorsFilter,
                    'categories' => $categories,
                ]
            );
            $lists[$activeListPanel->id] = $activeListPanel->getConfig();
        }

        // Archived
        $params = [
            'status' => [PKPSubmission::STATUS_DECLINED, PKPSubmission::STATUS_PUBLISHED, PKPSubmission::STATUS_SCHEDULED],
        ];
        if (empty(array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $userRoles))) {
            $params['assignedTo'] = (int) $currentUser->getId();
        }
        $archivedListPanel = new \APP\components\listPanels\SubmissionsListPanel(
            SUBMISSIONS_LIST_ARCHIVE,
            __('common.queue.long.submissionsArchived'),
            [
                'apiUrl' => $apiUrl,
                'getParams' => $params,
                'lazyLoad' => true,
                'includeIssuesFilter' => $includeIssuesFilter,
                'includeCategoriesFilter' => $includeCategoriesFilter,
                'includeAssignedEditorsFilter' => $includeAssignedEditorsFilter,
                'categories' => $categories,
            ]
        );
        $lists[$archivedListPanel->id] = $archivedListPanel->getConfig();

        $templateMgr->setState(['components' => $lists]);
        $templateMgr->assign([
            'pageTitle' => __('navigation.submissions'),
        ]);

        return $templateMgr->display('dashboard/index.tpl');
    }

    /**
     * View tasks tab
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
}
