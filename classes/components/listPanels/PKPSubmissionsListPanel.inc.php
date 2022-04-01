<?php
/**
 * @file components/listPanels/PKPSubmissionsListPanel.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PKPSubmissionsListPanel
 * @ingroup classes_components_list
 *
 * @brief A ListPanel component for displaying submissions in the dashboard
 */

namespace PKP\components\listPanels;

use APP\core\Application;
use APP\facades\Repo;
use APP\template\TemplateManager;
use PKP\components\forms\FieldAutosuggestPreset;
use PKP\components\forms\FieldSelectUsers;
use PKP\security\Role;
use PKP\submission\PKPSubmission;

use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;

abstract class PKPSubmissionsListPanel extends ListPanel
{
    /** @var string URL to the API endpoint where items can be retrieved */
    public $apiUrl = '';

    /** @var int Number of items to show at one time */
    public $count = 30;

    /** @var array Query parameters to pass if this list executes GET requests  */
    public $getParams = [];

    /** @var bool Should items be loaded after the component is mounted?  */
    public $lazyLoad = false;

    /** @var int Count of total items available for list */
    public $itemsMax = 0;

    /** @var bool Whether to show assigned to editors filter */
    public $includeAssignedEditorsFilter = false;

    /** @var bool Whether to show categories filter */
    public $includeCategoriesFilter = false;

    /** @var array List of all available categories */
    public $categories = [];

    /**
     * @copydoc ListPanel::getConfig()
     */
    public function getConfig()
    {
        $request = Application::get()->getRequest();
        $context = $request->getContext();

        $config = parent::getConfig();

        $config['apiUrl'] = $this->apiUrl;
        $config['count'] = $this->count;
        $config['getParams'] = $this->getParams;
        $config['lazyLoad'] = $this->lazyLoad;
        $config['itemsMax'] = $this->itemsMax;

        // URL to add a new submission
        if ($context->getData('disableSubmissions')) {
            $config['allowSubmissions'] = false;
        }

        $config['addUrl'] = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_PAGE,
            null,
            'submission',
            'wizard'
        );

        // URL to view info center for a submission
        $config['infoUrl'] = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_COMPONENT,
            null,
            'informationCenter.SubmissionInformationCenterHandler',
            'viewInformationCenter',
            null,
            ['submissionId' => '__id__']
        );

        // URL to assign a participant
        $config['assignParticipantUrl'] = $request->getDispatcher()->url(
            $request,
            Application::ROUTE_COMPONENT,
            null,
            'grid.users.stageParticipant.StageParticipantGridHandler',
            'addParticipant',
            null,
            ['submissionId' => '__id__', 'stageId' => '__stageId__']
        );

        $config['filters'] = [
            [
                'filters' => [
                    [
                        'param' => 'isOverdue',
                        'value' => true,
                        'title' => __('common.overdue'),
                    ],
                    [
                        'param' => 'isIncomplete',
                        'value' => true,
                        'title' => __('submissions.incomplete'),
                    ],
                ],
            ],
            [
                'heading' => __('settings.roles.stages'),
                'filters' => $this->getWorkflowStages(),
            ],
            [
                'heading' => __('submission.list.activity'),
                'filters' => [
                    [
                        'title' => __('submission.list.daysSinceLastActivity'),
                        'param' => 'daysInactive',
                        'value' => 30,
                        'min' => 1,
                        'max' => 180,
                        'filterType' => 'pkp-filter-slider',
                    ]
                ]
            ]
        ];

        if ($this->includeCategoriesFilter) {
            $categoryFilter = [];
            $categoryFilter = $this->getCategoryFilters($this->categories);
            if ($categoryFilter) {
                $config['filters'][] = $categoryFilter;
            }
        }

        if ($this->includeAssignedEditorsFilter) {
            $assignedEditorsField = new FieldSelectUsers('assignedTo', [
                'label' => __('editor.submissions.assignedTo'),
                'value' => [],
                'apiUrl' => $request->getDispatcher()->url(
                    $request,
                    Application::ROUTE_API,
                    $context->getPath(),
                    'users',
                    null,
                    null,
                    ['roleIds' => [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]]
                ),
            ]);
            $config['filters'][] = [
                'filters' => [
                    [
                        'title' => __('editor.submissions.assignedTo'),
                        'param' => 'assignedTo',
                        'value' => [],
                        'filterType' => 'pkp-filter-autosuggest',
                        'component' => $assignedEditorsField->component,
                        'autosuggestProps' => $assignedEditorsField->getConfig(),
                    ]
                ]
            ];
        }

        // Provide required constants
        $templateMgr = TemplateManager::getManager($request);
        $templateMgr->setConstants([
            'STATUS_QUEUED' => PKPSubmission::STATUS_QUEUED,
            'STATUS_PUBLISHED' => PKPSubmission::STATUS_PUBLISHED,
            'STATUS_DECLINED' => PKPSubmission::STATUS_DECLINED,
            'STATUS_SCHEDULED' => PKPSubmission::STATUS_SCHEDULED,
            'WORKFLOW_STAGE_ID_SUBMISSION' => WORKFLOW_STAGE_ID_SUBMISSION,
            'WORKFLOW_STAGE_ID_INTERNAL_REVIEW' => WORKFLOW_STAGE_ID_INTERNAL_REVIEW,
            'WORKFLOW_STAGE_ID_EXTERNAL_REVIEW' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'WORKFLOW_STAGE_ID_EDITING' => WORKFLOW_STAGE_ID_EDITING,
            'WORKFLOW_STAGE_ID_PRODUCTION' => WORKFLOW_STAGE_ID_PRODUCTION,
            'STAGE_STATUS_SUBMISSION_UNASSIGNED' => Repo::submission()::STAGE_STATUS_SUBMISSION_UNASSIGNED,
            'REVIEW_ROUND_STATUS_PENDING_REVIEWERS' => ReviewRound::REVIEW_ROUND_STATUS_PENDING_REVIEWERS,
            'REVIEW_ROUND_STATUS_REVIEWS_READY' => ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_READY,
            'REVIEW_ROUND_STATUS_REVIEWS_COMPLETED' => ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED,
            'REVIEW_ROUND_STATUS_REVIEWS_OVERDUE' => ReviewRound::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE,
            'REVIEW_ROUND_STATUS_REVISIONS_REQUESTED' => ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED,
            'REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED' => ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED,
            'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW' => ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW,
            'REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED' => ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED,
            'REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE,
            'REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE,
            'REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE,
            'REVIEW_ASSIGNMENT_STATUS_ACCEPTED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_ACCEPTED,
            'REVIEW_ASSIGNMENT_STATUS_RECEIVED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RECEIVED,
            'REVIEW_ASSIGNMENT_STATUS_COMPLETE' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_COMPLETE,
            'REVIEW_ASSIGNMENT_STATUS_THANKED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_THANKED,
            'REVIEW_ASSIGNMENT_STATUS_CANCELLED' => ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_CANCELLED,
            'REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY' => ReviewRound::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY,
            'REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED' => ReviewRound::REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED,
        ]);

        $templateMgr->setLocaleKeys([
            'common.lastActivity',
            'editor.submissionArchive.confirmDelete',
            'submission.list.empty',
            'submission.submit.newSubmissionSingle',
            'submission.review',
            'submissions.incomplete',
            'submission.list.assignEditor',
            'submission.list.copyeditsSubmitted',
            'submission.list.currentStage',
            'submission.list.discussions',
            'submission.list.dualWorkflowLinks',
            'submission.list.galleysCreated',
            'submission.list.infoCenter',
            'submission.list.reviewAssignment',
            'submission.list.responseDue',
            'submission.list.reviewCancelled',
            'submission.list.reviewComplete',
            'submission.list.reviewDue',
            'submission.list.reviewerWorkflowLink',
            'submission.list.reviewsCompleted',
            'submission.list.revisionsSubmitted',
            'submission.list.viewSubmission',
        ]);

        return $config;
    }

    /**
     * Compile the categories for passing as filters
     *
     * @param array $categories
     *
     * @return array
     */
    public function getCategoryFilters($categories = [])
    {
        $request = Application::get()->getRequest();

        if ($categories) {
            // Use an autosuggest field if the list of categories is too long
            if (count($categories) > 5) {
                $autosuggestField = new FieldAutosuggestPreset('categoryIds', [
                    'label' => __('category.category'),
                    'value' => [],
                    'options' => array_map(function ($category) {
                        return [
                            'value' => (int) $category['id'],
                            'label' => $category['title'],
                        ];
                    }, $categories),
                ]);
                return [
                    'filters' => [
                        [
                            'title' => __('category.category'),
                            'param' => 'categoryIds',
                            'filterType' => 'pkp-filter-autosuggest',
                            'component' => 'field-autosuggest-preset',
                            'value' => [],
                            'autosuggestProps' => $autosuggestField->getConfig(),
                        ]
                    ],
                ];
            }

            return [
                'heading' => __('category.category'),
                'filters' => array_map(function ($category) {
                    return [
                        'param' => 'categoryIds',
                        'value' => (int) $category['id'],
                        'title' => $category['title'],
                    ];
                }, $categories),
            ];
        }

        return [];
    }
}
