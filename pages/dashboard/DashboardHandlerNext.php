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

use APP\components\forms\publication\PublishForm;
use APP\core\Application;
use APP\facades\Repo;
use APP\handler\Handler;
use APP\template\TemplateManager;
use PKP\components\forms\dashboard\SubmissionFilters;
use PKP\controllers\grid\users\reviewer\PKPReviewerGridHandler;
use PKP\core\JSONMessage;
use PKP\core\PKPApplication;
use PKP\core\PKPRequest;
use PKP\decision\Decision;
use PKP\plugins\Hook;
use PKP\security\authorization\PKPSiteAccessPolicy;
use PKP\security\Role;
use PKP\submission\DashboardView;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submissionFile\SubmissionFile;
use PKP\components\forms\publication\ContributorForm;
use PKP\plugins\PluginRegistry;
use PKP\notification\Notification;

define('SUBMISSIONS_LIST_ACTIVE', 'active');
define('SUBMISSIONS_LIST_ARCHIVE', 'archive');
define('SUBMISSIONS_LIST_MY_QUEUE', 'myQueue');
define('SUBMISSIONS_LIST_UNASSIGNED', 'unassigned');

enum DashboardPage: string
{
    case EditorialDashboard = 'editorialDashboard';
    case MyReviewAssignments = 'myReviewAssignments';
    case MySubmissions = 'mySubmissions';
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

        if($this->dashboardPage === DashboardPage::EditorialDashboard) {
            $this->selectedRoleIds = [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_ASSISTANT];
        } elseif($this->dashboardPage === DashboardPage::MyReviewAssignments) {
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

        // ContributorsForm
        $contributorForm = new ContributorForm(
            'emit',
            [],
            null,
              $context

        );



        $selectRevisionDecisionForm = new \PKP\components\forms\decision\SelectRevisionDecisionForm();
        $selectRevisionRecommendationForm = new \PKP\components\forms\decision\SelectRevisionRecommendationForm();

        // Detect whether identifiers are enabled
        $identifiersEnabled = false;
        $pubIdPlugins = PluginRegistry::getPlugins('pubIds');
        foreach ($pubIdPlugins as $pubIdPlugin) {
            if ($pubIdPlugin->isObjectTypeEnabled('Publication', $request->getContext()->getId())) {
                $identifiersEnabled = true;
                break;
            }
        }

        // OJS specific, might need to be adjusted for OMP/OPS
        $paymentManager = Application::get()->getPaymentManager($context);
            
        $templateMgr->setState([
            'pageInitConfig' => [
                'selectRevisionDecisionForm' => $selectRevisionDecisionForm->getConfig(),
                'selectRevisionRecommendationForm' => $selectRevisionRecommendationForm->getConfig(),
                'dashboardPage' => $this->dashboardPage,
                'countPerPage' => $this->perPage,
                'filtersForm' => $filtersForm->getConfig(),
                'contributorForm' => $contributorForm->getConfig(),
                'views' => $this->getViews(),
                'columns' => $this->getColumns(),
                'publicationSettings' => [
                    'supportsCitations' => !!$context->getData('citations'),
                    'identifiersEnabled' => $identifiersEnabled,
                    'submissionPaymentsEnabled' => $paymentManager->publicationEnabled()
                ]
            ]
        ]);

        $templateMgr->assign([
            'pageComponent' => 'PageOJS',
            'pageTitle' => __('navigation.submissions'),
            'pageWidth' => TemplateManager::PAGE_WIDTH_FULL,
        ]);


        class_exists(\APP\components\forms\publication\AssignToIssueForm::class); // Force define of FORM_ASSIGN_TO_ISSUE

        $templateMgr->setConstants([
            'STAGE_STATUS_SUBMISSION_UNASSIGNED' => Repo::submission()::STAGE_STATUS_SUBMISSION_UNASSIGNED,
            'REVIEW_ASSIGNMENT_STATUS_DECLINED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_DECLINED,
            'REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE,
            'REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE,
            'REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE,
            'REVIEW_ASSIGNMENT_STATUS_ACCEPTED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_ACCEPTED,
            'REVIEW_ASSIGNMENT_STATUS_RECEIVED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RECEIVED,
            'REVIEW_ASSIGNMENT_STATUS_COMPLETE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE,
            'REVIEW_ASSIGNMENT_STATUS_THANKED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_THANKED,
            'REVIEW_ASSIGNMENT_STATUS_CANCELLED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_CANCELLED,
            'REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND,
            'REVIEW_ROUND_STATUS_REVISIONS_REQUESTED' => ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED,
            'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW' => ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW,
            'REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL' => ReviewRound::REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL,
            'REVIEW_ROUND_STATUS_ACCEPTED' => ReviewRound::REVIEW_ROUND_STATUS_ACCEPTED,
            'REVIEW_ROUND_STATUS_DECLINED' => ReviewRound::REVIEW_ROUND_STATUS_DECLINED,
            'REVIEW_ROUND_STATUS_PENDING_REVIEWERS' => ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS,
            'REVIEW_ROUND_STATUS_PENDING_REVIEWS' => ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWS,
            'REVIEW_ROUND_STATUS_REVIEWS_READY' => ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_READY,
            'REVIEW_ROUND_STATUS_REVIEWS_COMPLETED' => ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED,
            'REVIEW_ROUND_STATUS_REVIEWS_OVERDUE' => ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE,
            'REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED' => ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED,
            'REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS' => ReviewRound::REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS,
            'REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY' => ReviewRound::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY,
            'REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED' => ReviewRound::REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED,
            'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED' => ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED,
            'REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW' => ReviewRound::REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW,
            'SUBMISSION_REVIEW_METHOD_ANONYMOUS' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS,
            'SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS,
            'SUBMISSION_REVIEW_METHOD_OPEN' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN,

            'SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT,
            'SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS,
            'SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE,
            'SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE,
            'SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE,
            'SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS' => ReviewAssignment::SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS,

            'DECISION_ACCEPT' => Decision::ACCEPT,
            'DECISION_DECLINE' => Decision::DECLINE,
            'DECISION_CANCEL_REVIEW_ROUND' => Decision::CANCEL_REVIEW_ROUND,
            'DECISON_PENDING_REVISIONS' => Decision::PENDING_REVISIONS,
            'DECISION_EXTERNAL_REVIEW' => Decision::EXTERNAL_REVIEW,
            'DECISION_SKIP_EXTERNAL_REVIEW' => Decision::SKIP_EXTERNAL_REVIEW,
            'DECISION_INITIAL_DECLINE' => Decision::INITIAL_DECLINE,
            'DECISION_SEND_TO_PRODUCTION' => Decision::SEND_TO_PRODUCTION,
            'DECISION_BACK_FROM_COPYEDITING' => Decision::BACK_FROM_COPYEDITING,
            'DECISION_NEW_EXTERNAL_ROUND' => Decision::NEW_EXTERNAL_ROUND,
            'DECISION_BACK_FROM_PRODUCTION' => Decision::BACK_FROM_PRODUCTION,

            'DECISION_RECOMMEND_ACCEPT' => Decision::RECOMMEND_ACCEPT,
            'DECISION_RECOMMEND_DECLINE' => Decision::RECOMMEND_DECLINE,
            'DECISION_RECOMMEND_ACCEPT' => Decision::RECOMMEND_ACCEPT,
            'DECISION_RECOMMEND_PENDING_REVISIONS' => Decision::RECOMMEND_PENDING_REVISIONS,
            'DECISION_RECOMMEND_RESUBMIT' => Decision::RECOMMEND_RESUBMIT,


            'SUBMISSION_FILE_SUBMISSION' => SubmissionFile::SUBMISSION_FILE_SUBMISSION,
            'SUBMISSION_FILE_REVIEW_FILE' => SubmissionFile::SUBMISSION_FILE_REVIEW_FILE,
            'SUBMISSION_FILE_REVIEW_REVISION' => SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION,
            'SUBMISSION_FILE_FINAL' => SubmissionFile::SUBMISSION_FILE_FINAL,
            'SUBMISSION_FILE_REVIEW_ATTACHMENT' => SubmissionFile::SUBMISSION_FILE_REVIEW_ATTACHMENT,
            'SUBMISSION_FILE_COPYEDIT' => SubmissionFile::SUBMISSION_FILE_COPYEDIT,
            'SUBMISSION_FILE_PRODUCTION_READY' => SubmissionFile::SUBMISSION_FILE_PRODUCTION_READY,
            'SUBMISSION_FILE_PROOF' => SubmissionFile::SUBMISSION_FILE_PROOF,
            'SUBMISSION_FILE_JATS' => SubmissionFile::SUBMISSION_FILE_JATS,
            'FORM_ASSIGN_TO_ISSUE' => FORM_ASSIGN_TO_ISSUE,
            'FORM_PUBLISH' => PublishForm::FORM_PUBLISH,

            'REVIEWER_SELECT_ADVANCED_SEARCH' => PKPReviewerGridHandler::REVIEWER_SELECT_ADVANCED_SEARCH,

            'ROLE_ID_AUTHOR' => Role::ROLE_ID_AUTHOR,

            // ASSOC
            'ASSOC_TYPE_REVIEW_ASSIGNMENT' => PKPApplication::ASSOC_TYPE_REVIEW_ASSIGNMENT,
            'ASSOC_TYPE_REPRESENTATION' => PKPApplication::ASSOC_TYPE_REPRESENTATION,
            'ASSOC_TYPE_SUBMISSION' => PKPApplication::ASSOC_TYPE_SUBMISSION,
            // NOTIFICATIONS
            'NOTIFICATION_LEVEL_NORMAL' => Notification::NOTIFICATION_LEVEL_NORMAL,
            'NOTIFICATION_LEVEL_TRIVIAL' => Notification::NOTIFICATION_LEVEL_TRIVIAL,

            'NOTIFICATION_TYPE_VISIT_CATALOG' => Notification::NOTIFICATION_TYPE_VISIT_CATALOG,
            'NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER' => Notification::NOTIFICATION_TYPE_ASSIGN_PRODUCTIONUSER,
            'NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS' => Notification::NOTIFICATION_TYPE_AWAITING_REPRESENTATIONS
        ]);

        $templateMgr->display('dashboard/editors.tpl');
    }


    /**
     * Display about index page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function editorial($args, $request)
    {
        return $this->index($args, $request);
    }

    /**
     * Display Review Assignments page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function reviewAssignments($args, $request)
    {
        return $this->index($args, $request);
    }

    /**
     * Display My Submissions page.
     *
     * @param PKPRequest $request
     * @param array $args
     */
    public function mySubmissions($args, $request)
    {
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

        if($this->dashboardPage === DashboardPage::MyReviewAssignments) {
            $columns = [
                $this->createColumn('id', __('common.id'), 'CellReviewAssignmentId', true),
                $this->createColumn('title', __('navigation.submissions'), 'CellReviewAssignmentTitle'),
                $this->createColumn('activity', __('stats.editorialActivity'), 'CellReviewAssignmentActivity'),
                $this->createColumn('actions', __('admin.jobs.list.actions'), 'CellReviewAssignmentActions')
            ];
        } elseif($this->dashboardPage === DashboardPage::MySubmissions) {

            $columns = [
                $this->createColumn('id', __('common.id'), 'CellSubmissionId', true),
                $this->createColumn('title', __('navigation.submissions'), 'CellSubmissionTitle'),
                $this->createColumn('stage', __('workflow.stage'), 'CellSubmissionStage'),
                $this->createColumn('activity', __('stats.editorialActivity'), 'CellSubmissionActivity'),
                $this->createColumn('actions', __('admin.jobs.list.actions'), 'CellSubmissionActions')
            ];
        } else {
            $columns = [
                $this->createColumn('id', __('common.id'), 'CellSubmissionId', true),
                $this->createColumn('title', __('navigation.submissions'), 'CellSubmissionTitle'),
                $this->createColumn('stage', __('workflow.stage'), 'CellSubmissionStage'),
                $this->createColumn('days', __('editor.submission.days'), 'CellSubmissionDays'),
                $this->createColumn('activity', __('stats.editorialActivity'), 'CellSubmissionActivity'),
                $this->createColumn('actions', __('admin.jobs.list.actions'), 'CellSubmissionActions')
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
