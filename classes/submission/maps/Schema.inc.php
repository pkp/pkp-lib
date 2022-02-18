<?php
/**
 * @file classes/submission/maps/Schema.inc.php
 *
 * Copyright (c) 2014-2020 Simon Fraser University
 * Copyright (c) 2000-2020 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class submission
 *
 * @brief Map submissions to the properties defined in the submission schema
 */

namespace PKP\submission\maps;

use APP\core\Application;
use APP\core\Request;
use APP\facades\Repo;
use APP\submission\Submission;

use Illuminate\Support\Enumerable;

use PKP\context\Context;
use PKP\db\DAORegistry;
use PKP\plugins\HookRegistry;
use PKP\plugins\PluginRegistry;
use PKP\services\PKPSchemaService;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submissionFile\SubmissionFile;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION;

    /** @var array The user groups for this context. */
    public array $userGroups;

    /**
     * Get extra property names used in the submissions list
     */
    protected function getSubmissionsListProps(): array
    {
        PluginRegistry::loadCategory('pubIds', true);

        $props = [
            '_href',
            'contextId',
            'currentPublicationId',
            'dateLastActivity',
            'dateSubmitted',
            'id',
            'lastModified',
            'publications',
            'reviewAssignments',
            'reviewRounds',
            'stageId',
            'stages',
            'status',
            'statusLabel',
            'submissionProgress',
            'urlAuthorWorkflow',
            'urlEditorialWorkflow',
            'urlWorkflow',
            'urlPublished',
        ];

        HookRegistry::call('Submission::getSubmissionsListProps', [&$props]);

        return $props;
    }

    /**
     * Map a submission
     *
     * Includes all properties in the submission schema.
     */
    public function map(Submission $item, array $userGroups): array
    {
        $this->userGroups = $userGroups;
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a submission
     *
     * Includes properties with the apiSummary flag in the submission schema.
     */
    public function summarize(Submission $item, array $userGroups): array
    {
        $this->userGroups = $userGroups;
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Submissions
     *
     * @see self::map
     */
    public function mapMany(Enumerable $collection, array $userGroups): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        return $collection->map(function ($item) {
            return $this->map($item, $this->userGroups);
        });
    }

    /**
     * Summarize a collection of Submissions
     *
     * @see self::summarize
     */
    public function summarizeMany(Enumerable $collection, array $userGroups): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        return $collection->map(function ($item) {
            return $this->summarize($item, $this->userGroups);
        });
    }

    /**
     * Map a submission with extra properties for the submissions list
     */
    public function mapToSubmissionsList(Submission $item, array $userGroups): array
    {
        $this->userGroups = $userGroups;
        return $this->mapByProperties($this->getSubmissionsListProps(), $item);
    }

    /**
     * Map a collection of submissions with extra properties for the submissions list
     *
     * @see self::map
     */
    public function mapManyToSubmissionsList(Enumerable $collection, array $userGroups): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        return $collection->map(function ($item) {
            return $this->mapToSubmissionsList($item, $this->userGroups);
        });
    }

    /**
     * Map a submission with only the title, authors, and URLs for the stats list
     */
    public function mapToStats(Submission $submission): array
    {
        $props = $this->mapByProperties([
            '_href',
            'id',
            'urlWorkflow',
            'urlPublished',
        ], $submission);

        $currentPublication = $submission->getCurrentPublication();
        if ($currentPublication) {
            $props['authorsStringShort'] = $currentPublication->getShortAuthorString();
            $props['fullTitle'] = $currentPublication->getFullTitles();
        }

        return $props;
    }

    /**
     * Summarize a submission without publication details
     */
    public function summarizeWithoutPublication(Submission $item): array
    {
        $props = array_filter($this->getSummaryProps(), function ($prop) {
            return $prop !== 'publications';
        });
        return $this->mapByProperties($props, $item);
    }

    /**
     * Map schema properties of a Submission to an assoc array
     */
    protected function mapByProperties(array $props, Submission $submission): array
    {
        $output = [];

        if (in_array('publications', $props)) {
            $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
            $currentUserReviewAssignment = $reviewAssignmentDao->getLastReviewRoundReviewAssignmentByReviewer(
                $submission->getId(),
                $this->request->getUser()->getId()
            );
            $anonymize = $currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;
        }

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = $this->getApiUrl(
                        'submissions/' . $submission->getId(),
                        $this->context->getData('urlPath')
                    );
                    break;
                case 'publications':
                    $output[$prop] = Repo::publication()->getSchemaMap($submission, $this->userGroups)
                        ->summarizeMany($submission->getData('publications'), $anonymize);
                    break;
                case 'reviewAssignments':
                    $output[$prop] = $this->getPropertyReviewAssignments($submission);
                    break;
                case 'reviewRounds':
                    $output[$prop] = $this->getPropertyReviewRounds($submission);
                    break;
                case 'stages':
                    $output[$prop] = $this->getPropertyStages($submission);
                    break;
                case 'statusLabel':
                    $output[$prop] = __($submission->getStatusKey());
                    break;
                case 'urlAuthorWorkflow':
                    $output[$prop] = $this->request->getDispatcher()->url(
                        $this->request,
                        Application::ROUTE_PAGE,
                        $this->context->getData('urlPath'),
                        'authorDashboard',
                        'submission',
                        $submission->getId()
                    );
                    break;
                case 'urlEditorialWorkflow':
                    $output[$prop] = $this->request->getDispatcher()->url(
                        $this->request,
                        Application::ROUTE_PAGE,
                        $this->context->getData('urlPath'),
                        'workflow',
                        'access',
                        $submission->getId()
                    );
                    break;
                case 'urlWorkflow':
                    $output[$prop] = Repo::submission()->getWorkflowUrlByUserRoles($submission);
                    break;
                default:
                    $output[$prop] = $submission->getData($prop);
                    break;
            }
        }

        return $output;
    }

    /**
     * Get details about the review assignments for a submission
     */
    protected function getPropertyReviewAssignments(Submission $submission): array
    {
        $reviewAssignmentDao = DAORegistry::getDAO('ReviewAssignmentDAO'); /** @var ReviewAssignmentDAO $reviewAssignmentDao */
        $reviewAssignments = $reviewAssignmentDao->getBySubmissionId($submission->getId());

        $reviews = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            // @todo for now, only show reviews that haven't been
            // declined or cancelled
            if ($reviewAssignment->getDeclined() || $reviewAssignment->getCancelled()) {
                continue;
            }

            $request = Application::get()->getRequest();
            $currentUser = $request->getUser();
            $context = $request->getContext();
            $dateFormatShort = $context->getLocalizedDateFormatShort();
            $due = is_null($reviewAssignment->getDateDue()) ? null : strftime($dateFormatShort, strtotime($reviewAssignment->getDateDue()));
            $responseDue = is_null($reviewAssignment->getDateResponseDue()) ? null : strftime($dateFormatShort, strtotime($reviewAssignment->getDateResponseDue()));

            $reviews[] = [
                'id' => (int) $reviewAssignment->getId(),
                'isCurrentUserAssigned' => $currentUser->getId() == (int) $reviewAssignment->getReviewerId(),
                'statusId' => (int) $reviewAssignment->getStatus(),
                'status' => __($reviewAssignment->getStatusKey()),
                'due' => $due,
                'responseDue' => $responseDue,
                'round' => (int) $reviewAssignment->getRound(),
                'roundId' => (int) $reviewAssignment->getReviewRoundId(),
            ];
        }

        return $reviews;
    }

    /**
     * Get details about the review rounds for a submission
     */
    protected function getPropertyReviewRounds(Submission $submission): array
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
        $reviewRounds = $reviewRoundDao->getBySubmissionId($submission->getId())->toIterator();

        $rounds = [];
        foreach ($reviewRounds as $reviewRound) {
            $rounds[] = [
                'id' => $reviewRound->getId(),
                'round' => $reviewRound->getRound(),
                'stageId' => $reviewRound->getStageId(),
                'statusId' => $reviewRound->determineStatus(),
                'status' => __($reviewRound->getStatusKey()),
            ];
        }

        return $rounds;
    }

    /**
     * Get details about a submission's stage(s)
     *
     * @return array
     * [
     *  {
     *  `id` int stage id
     *  `label` string translated stage name
     *  `queries` array [{
     *    `id` int query id
     *    `assocType` int
     *    `assocId` int
     *    `stageId` int
     *    `seq` int
     *    `closed` bool
     *   }]
     *  `statusId` int stage status. note: on review stage, this refers to the
     *    status of the latest round.
     *  `status` string translated stage status name
     *  `files` array {
     *    `count` int number of files attached to stage. note: this only counts
     *      revision files.
     *   }
     *  ]
     */
    public function getPropertyStages(Submission $submission): array
    {
        $stageIds = Application::get()->getApplicationStages();
        $currentUser = Application::get()->getRequest()->getUser();
        $context = Application::get()->getRequest()->getContext();
        $contextId = $context ? $context->getId() : Application::CONTEXT_ID_NONE;

        $stages = [];
        foreach ($stageIds as $stageId) {
            $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
            $stage = [
                'id' => (int) $stageId,
                'label' => __($workflowStageDao->getTranslationKeyFromId($stageId)),
                'isActiveStage' => $submission->getData('stageId') == $stageId,
            ];

            // Discussions in this stage
            $stage['queries'] = [];
            $request = Application::get()->getRequest();
            $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
            $queries = $queryDao->getByAssoc(
                Application::ASSOC_TYPE_SUBMISSION,
                $submission->getId(),
                $stageId,
                $request->getUser()->getId() // Current user restriction should prevent unauthorized access
            );

            while ($query = $queries->next()) {
                $stage['queries'][] = [
                    'id' => (int) $query->getId(),
                    'assocType' => (int) $query->getAssocType(),
                    'assocId' => (int) $query->getAssocId(),
                    'stageId' => $stageId,
                    'seq' => (int) $query->getSequence(),
                    'closed' => (bool) $query->getIsClosed(),
                ];
            }

            $currentUserAssignedRoles = [];
            if ($currentUser) {
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $stageId);
                $userGroupDao = DAORegistry::getDAO('UserGroupDAO'); /** @var UserGroupDAO $userGroupDao */
                while ($stageAssignment = $stageAssignmentsResult->next()) {
                    $userGroup = $userGroupDao->getById($stageAssignment->getUserGroupId(), $contextId);
                    $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
                }
            }
            $stage['currentUserAssignedRoles'] = array_values(array_unique($currentUserAssignedRoles));

            // Stage-specific statuses
            switch ($stageId) {

                case WORKFLOW_STAGE_ID_SUBMISSION:
                    $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                    $assignedEditors = $stageAssignmentDao->editorAssignedToStage($submission->getId(), $stageId);
                    if (!$assignedEditors) {
                        $stage['statusId'] = Repo::submission()::STAGE_STATUS_SUBMISSION_UNASSIGNED;
                        $stage['status'] = __('submissions.queuedUnassigned');
                    }

                    // Submission stage never has revisions
                    $stage['files'] = [
                        'count' => 0,
                    ];
                    break;

                case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                    $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                    $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
                    if ($reviewRound) {
                        $stage['statusId'] = $reviewRound->determineStatus();
                        $stage['status'] = __($reviewRound->getStatusKey());

                        $collector = Repo::submissionFile()
                            ->getCollector()
                            ->filterBySubmissionIds([$submission->getId()])
                            ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION])
                            ->filterByReviewRoundIds([$reviewRound->getId()]);
                        // Revision files in this round.
                        $stage['files'] = [
                            'count' => Repo::submissionFile()->getCount($collector),
                        ];

                        // See if the  curent user can only recommend:
                        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                        $user = $request->getUser();
                        $editorsStageAssignments = $stageAssignmentDao->getEditorsAssignedToStage($submission->getId(), $stageId);
                        // if the user is assigned several times in the editorial role, and
                        // one of the assignments have recommendOnly option set, consider it here
                        $stage['currentUserCanRecommendOnly'] = false;
                        foreach ($editorsStageAssignments as $editorsStageAssignment) {
                            if ($editorsStageAssignment->getUserId() == $user->getId() && $editorsStageAssignment->getRecommendOnly()) {
                                $stage['currentUserCanRecommendOnly'] = true;
                                break;
                            }
                        }
                    } else {
                        // workaround for pkp/pkp-lib#4231, pending formal data model
                        $stage['files'] = [
                            'count' => 0
                        ];
                    }
                    break;

                // Get revision files for editing and production stages.
                // Review rounds are handled separately in the review stage below.
                case WORKFLOW_STAGE_ID_EDITING:
                case WORKFLOW_STAGE_ID_PRODUCTION:
                    $fileStages = [WORKFLOW_STAGE_ID_EDITING ? SubmissionFile::SUBMISSION_FILE_COPYEDIT : SubmissionFile::SUBMISSION_FILE_PROOF];
                    $collector = Repo::submissionFile()
                        ->getCollector()
                        ->filterBySubmissionIds([$submission->getId()])
                        ->filterByFileStages($fileStages);
                    // Revision files in this round.
                    $stage['files'] = [
                        'count' => Repo::submissionFile()->getCount($collector),
                    ];
                    break;
            }

            $stages[] = $stage;
        }

        return $stages;
    }
}
