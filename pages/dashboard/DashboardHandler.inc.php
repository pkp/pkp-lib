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

use APP\handler\Handler;
use APP\template\TemplateManager;

use PKP\security\authorization\PKPSiteAccessPolicy;
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
            [ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_AUTHOR, ROLE_ID_REVIEWER, ROLE_ID_ASSISTANT],
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
     * @param $request PKPRequest
     * @param $args array
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

        $includeIssuesFilter = array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT], $userRoles);
        $includeAssignedEditorsFilter = array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], $userRoles);
        $includeCategoriesFilter = array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER, ROLE_ID_SUB_EDITOR, ROLE_ID_ASSISTANT], $userRoles);

        // Get all available categories
        $categories = [];
        $categoryDao = \DAORegistry::getDAO('CategoryDAO'); /** @var CategoryDAO $categoryDao */
        $categoryIterator = $categoryDao->getByContextId($context->getId())->toAssociativeArray();
        foreach ($categoryIterator as $category) {
            $categories[] = [
                'id' => $category->getId(),
                'title' => $category->getLocalizedTitle(),
            ];
        }

        // My Queue
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
                'categories' => $categories,
            ]
        );
        $myQueueListPanel->set([
            'items' => $myQueueListPanel->getItems($request),
            'itemsMax' => $myQueueListPanel->getItemsMax()
        ]);
        $lists[$myQueueListPanel->id] = $myQueueListPanel->getConfig();

        if (!empty(array_intersect([ROLE_ID_SITE_ADMIN, ROLE_ID_MANAGER], $userRoles))) {

            // Unassigned
            $unassignedListPanel = new \APP\components\listPanels\SubmissionsListPanel(
                SUBMISSIONS_LIST_UNASSIGNED,
                __('common.queue.long.submissionsUnassigned'),
                [
                    'apiUrl' => $apiUrl,
                    'getParams' => [
                        'status' => PKPSubmission::STATUS_QUEUED,
                        'assignedTo' => -1,
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
        if (empty(array_intersect([ROLE_ID_MANAGER, ROLE_ID_SITE_ADMIN], $userRoles))) {
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
     * @param $args array
     * @param $request PKPRequest
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
     * Setup common template variables.
     *
     * @param $request PKPRequest
     */
    public function setupTemplate($request = null)
    {
        AppLocale::requireComponents(LOCALE_COMPONENT_APP_MANAGER, LOCALE_COMPONENT_PKP_MANAGER, LOCALE_COMPONENT_APP_SUBMISSION, LOCALE_COMPONENT_PKP_SUBMISSION);
        parent::setupTemplate($request);
    }
}
