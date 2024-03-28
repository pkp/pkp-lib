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
use PKP\core\PKPRequest;
use PKP\plugins\Hook;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\submission\DashboardView;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;

define('SUBMISSIONS_LIST_ACTIVE', 'active');
define('SUBMISSIONS_LIST_ARCHIVE', 'archive');
define('SUBMISSIONS_LIST_MY_QUEUE', 'myQueue');
define('SUBMISSIONS_LIST_UNASSIGNED', 'unassigned');

enum DashboardPage: string
{
    case EDITORIAL_DASHBOARD = 'EDITORIAL_DASHBOARD';
    case MY_REVIEW_ASSIGNMENTS = 'MY_REVIEW_ASSIGNMENTS';
    case MY_SUBMISSIONS = 'MY_SUBMISSIONS';
}


class DashboardHandlerNext extends Handler
{
    /** @copydoc PKPHandler::_isBackendPage */
    public $_isBackendPage = true;

    public int $perPage = 30;


    /** Identify in which context is looking at the submissions */
    public DashboardPage $dashboardPage;

    /** 
     * editorial, review_assignments
     */
    public array $selectedRoleIds = [];
    /**
     * Constructor
     */
    public function __construct(DashboardPage $dashboardPage)
    {
        parent::__construct();

        $this->dashboardPage = $dashboardPage;

        if($this->dashboardPage === DashboardPage::EDITORIAL_DASHBOARD) {
            $this->selectedRoleIds = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
        } else if($this->dashboardPage === DashboardPage::MY_REVIEW_ASSIGNMENTS)  {
            $this->selectedRoleIds = [Role::ROLE_ID_REVIEWER];
        } else {
             $this->selectedRoleIds = [Role::ROLE_ID_AUTHOR];
        }
        
        $this->addRoleAssignment(
            [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT],
            ['index', 'editorial']
        );

        $this->addRoleAssignment(
            Role::ROLE_ID_REVIEWER,
            ['reviewAssignments']
        );

        $this->addRoleAssignment(
            Role::ROLE_ID_AUTHOR,
            ['mySubmissions']
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


        $templateMgr->setState([
            'pageInitConfig' => [
                'dashboardPage' => $this->dashboardPage,
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
                'filtersForm' => $filtersForm->getConfig(),
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
        'REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE,
		'REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE,
		'REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE,
		'REVIEW_ASSIGNMENT_STATUS_ACCEPTED'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_ACCEPTED,
		'REVIEW_ASSIGNMENT_STATUS_RECEIVED'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RECEIVED,
		'REVIEW_ASSIGNMENT_STATUS_COMPLETE'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE,
		'REVIEW_ASSIGNMENT_STATUS_THANKED'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_THANKED,
		'REVIEW_ASSIGNMENT_STATUS_CANCELLED'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_CANCELLED,
		'REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND'=> ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND,
		'REVIEW_ROUND_STATUS_PENDING_REVIEWERS'=> ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS,
		'REVIEW_ROUND_STATUS_REVIEWS_READY'=> ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_READY,
		'REVIEW_ROUND_STATUS_REVIEWS_COMPLETED'=> ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED,
		'REVIEW_ROUND_STATUS_REVIEWS_OVERDUE'=> ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE,
		'REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED'=> ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED,
		'REVIEW_ROUND_STATUS_REVISIONS_REQUESTED'=>ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED,
		'SUBMISSION_REVIEW_METHOD_ANONYMOUS'=> ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS,
		'SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS'=> ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS,
		'SUBMISSION_REVIEW_METHOD_OPEN'=> ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN,

		'SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT,
		'SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS,
		'SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE,
		'SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE,
		'SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE,
		'SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS,

        ]);

        $templateMgr->display('dashboard/editors.tpl');
    }


    /**
     * Display about index page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function editorial($args, $request){
        return $this->index($args, $request);
    }

    /**
     * Display Review Assignments page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function reviewAssignments($args, $request){
        return $this->index($args, $request);
    }

    /**
     * Display My Submissions page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function mySubmissions($args, $request){
        return $this->index($args, $request);
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
        $dashboardViews = Repo::submission()->getDashboardViews($context, $user, $this->selectedRoleIds);
        $viewsData = $dashboardViews->map(fn (DashboardView $dashboardView) => $dashboardView->getData())->values()->toArray();

        Hook::call('Dashboard::views', [&$viewsData, $userRoles]);

        return $viewsData;
    }

    /**
     * Define the columns in the submissions table
     *
     * @hook Dashboard::columns [[&$columns, $userRoles]]
     */
    public function getColumns(): array
    {
        $columns = [];

        if($this->dashboardPage === DashboardPage::MY_REVIEW_ASSIGNMENTS) {
            $columns = [
                $this->createColumn('id', __('common.id'), 'ColumnReviewAssignmentId', true),
                $this->createColumn('title', __('navigation.submissions'), 'ColumnReviewAssignmentTitle'),
                $this->createColumn('activity', __('stats.editorialActivity'), 'ColumnReviewAssignmentActivity'),
                $this->createColumn('actions', __('admin.jobs.list.actions'), 'ColumnReviewAssignmentActions')
            ];
        }
        else {
            $columns = [
                $this->createColumn('id', __('common.id'), 'ColumnSubmissionId', true),
                $this->createColumn('title', __('navigation.submissions'), 'ColumnSubmissionTitle'),
                $this->createColumn('stage', __('workflow.stage'), 'ColumnSubmissionStage'),
                $this->createColumn('days', __('editor.submission.days'), 'ColumnSubmissionDays'),
                $this->createColumn('activity', __('stats.editorialActivity'), 'ColumnSubmissionActivity'),
                $this->createColumn('actions', __('admin.jobs.list.actions'), 'ColumnSubmissionActions')
            ];
        }

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
