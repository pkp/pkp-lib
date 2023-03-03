<?php
/**
 * @file classes/submission/maps/Schema.php
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
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\query\QueryDAO;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\stageAssignment\StageAssignmentDAO;
use PKP\submission\Genre;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\userGroup\UserGroup;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION;

    /** @var LazyCollection<UserGroup> The user groups for this context. */
    public LazyCollection $userGroups;

    /** @var Genre[] The file genres in this context. */
    public array $genres;

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

        Hook::call('Submission::getSubmissionsListProps', [&$props]);

        return $props;
    }

    /**
     * Map a submission
     *
     * Includes all properties in the submission schema.
     *
     * @param LazyCollection<UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     */
    public function map(Submission $item, LazyCollection $userGroups, array $genres): array
    {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        return $this->mapByProperties($this->getProps(), $item);
    }

    /**
     * Summarize a submission
     *
     * Includes properties with the apiSummary flag in the submission schema.
     *
     * @param LazyCollection<UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     */
    public function summarize(Submission $item, LazyCollection $userGroups, array $genres): array
    {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        return $this->mapByProperties($this->getSummaryProps(), $item);
    }

    /**
     * Map a collection of Submissions
     *
     * @see self::map
     *
     * @param LazyCollection<UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     */
    public function mapMany(Enumerable $collection, LazyCollection $userGroups, array $genres): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        return $collection->map(function ($item) {
            return $this->map($item, $this->userGroups, $this->genres);
        });
    }

    /**
     * Summarize a collection of Submissions
     *
     * @see self::summarize
     *
     * @param LazyCollection<UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     */
    public function summarizeMany(Enumerable $collection, LazyCollection $userGroups, array $genres): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        return $collection->map(function ($item) {
            return $this->summarize($item, $this->userGroups, $this->genres);
        });
    }

    /**
     * Map a submission with extra properties for the submissions list
     *
     * @param LazyCollection<UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     */
    public function mapToSubmissionsList(Submission $item, LazyCollection $userGroups, array $genres): array
    {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        return $this->mapByProperties($this->getSubmissionsListProps(), $item);
    }

    /**
     * Map a collection of submissions with extra properties for the submissions list
     *
     * @see self::map
     *
     * @param LazyCollection<UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     */
    public function mapManyToSubmissionsList(Enumerable $collection, LazyCollection $userGroups, array $genres): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        return $collection->map(function ($item) {
            return $this->mapToSubmissionsList($item, $this->userGroups, $this->genres);
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
            $props['fullTitle'] = $currentPublication->getFullTitles('html');
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
                    $output[$prop] = Repo::submission()->getUrlApi($this->context, $submission->getId());
                    break;
                case 'publications':
                    $output[$prop] = Repo::publication()->getSchemaMap($submission, $this->userGroups, $this->genres)
                        ->summarizeMany($submission->getData('publications'), $anonymize)->values();
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
                    $output[$prop] = Repo::submission()->getUrlAuthorWorkflow($this->context, $submission->getId());
                    break;
                case 'urlEditorialWorkflow':
                    $output[$prop] = Repo::submission()->getUrlEditorialWorkflow($this->context, $submission->getId());
                    break;
                case 'urlSubmissionWizard':
                    $output[$prop] = Repo::submission()->getUrlSubmissionWizard($this->context, $submission->getId());
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
            $ask = is_null($reviewAssignment->getDateAssigned()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateAssigned()));
            $reminded = is_null($reviewAssignment->getDateReminded()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateReminded()));
            $due = is_null($reviewAssignment->getDateDue()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateDue()));
            $responseDue = is_null($reviewAssignment->getDateResponseDue()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateResponseDue()));
            $reviewer = Repo::user()->get($reviewAssignment->getReviewerId());
            $fullName = $reviewer->getFullName();

            $reviews[] = [
                'id' => (int) $reviewAssignment->getId(),
                'fullName' => $fullName,
                'isCurrentUserAssigned' => $currentUser->getId() == (int) $reviewAssignment->getReviewerId(),
                'statusId' => (int) $reviewAssignment->getStatus(),
                'status' => __($reviewAssignment->getStatusKey()),
                'ask' => $ask,
                'reminded' => $reminded,
                'due' => $due,
                'responseDue' => $responseDue,
                'round' => (int) $reviewAssignment->getRound(),
                'roundId' => (int) $reviewAssignment->getReviewRoundId(),
                'recommendation' => $reviewAssignment->getRecommendation() ? $reviewAssignment->getLocalizedRecommendation() : NULL, 
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
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        $context = $request->getContext();

        $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
        $stageAssignments = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId() ?? 0)->toArray();

        $queryDao = DAORegistry::getDAO('QueryDAO'); /** @var QueryDAO $queryDao */
        $openPerStage = $queryDao->countOpenPerStage($submission->getId(), [$request->getUser()->getId()]);

        $stages = [];
        foreach ($stageIds as $stageId) {
            $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
            $stage = [
                'id' => (int) $stageId,
                'label' => __($workflowStageDao->getTranslationKeyFromId($stageId)),
                'isActiveStage' => $submission->getData('stageId') == $stageId,
                'openQueryCount' => $openPerStage[$stageId],
            ];

            $currentUserAssignedRoles = [];
            if ($currentUser) {
                /** @var StageAssignment $stageAssignment */
                foreach ($stageAssignments as $stageAssignment) {
                    $userGroup = $this->getUserGroup($stageAssignment->getUserGroupId());
                    if ($userGroup) {
                        $currentUserAssignedRoles[] = $userGroup->getRoleId();
                    }
                }
                $stageAssignmentDao = DAORegistry::getDAO('StageAssignmentDAO'); /** @var StageAssignmentDAO $stageAssignmentDao */
                $stageAssignmentsResult = $stageAssignmentDao->getBySubmissionAndUserIdAndStageId($submission->getId(), $currentUser->getId(), $stageId);
                while ($stageAssignment = $stageAssignmentsResult->next()) {
                    $userGroup = Repo::userGroup()->get($stageAssignment->getUserGroupId());
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

                        // Revision files in this round.
                        $stage['files'] = [
                            'count' => Repo::submissionFile()->getCollector()
                                ->filterBySubmissionIds([$submission->getId()])
                                ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION])
                                ->filterByReviewRoundIds([$reviewRound->getId()])
                                ->getCount()
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
                    // Revision files in this round.
                    $stage['files'] = [
                        'count' => Repo::submissionFile()->getCollector()
                            ->filterBySubmissionIds([$submission->getId()])
                            ->filterByFileStages($fileStages)
                            ->getCount()
                    ];
                    break;
            }

            $stages[] = $stage;
        }

        return $stages;
    }

    /**
     * Get list of columns for submission list as a table
     * @return array
     * ['columnId' array [{
     *   'name' string
     *   'label' string translated column name
     *   'value' string corresponding submission value
     *   'orderBy' string corresponding to columId if orderBy by default (optionnal)
     *   'initialOrderDirection' bool (optionnal)
     * }],
     * ...
     * ]
     */
    static public function getPropertyColumnsName(): array
    {
        $columnsProperties = [ 
            'id' => [ 'name' => 'id',
                'label' => __('article.submissionId'),
                'value' => 'id',
                ],
            'title' => [ 'name' => 'title',
                'label' => __('common.title'),
                'value' => 'title',
                ],
            'openDiscussion' => [ 'name' => 'openDiscussion',
                'label' => __('submission.list.discussions'),
                'value' => 'openDiscussion',
                ],
            'stage' => [ 'name' => 'stage',
                'label' => __('workflow.stage'),
                'value' => 'stage',
                ],
            'dateLastActivity' => [ 'name' => 'lastActivity',
                'label' => __('common.dateModified'),
                'value' => 'dateLastActivity',
                'orderBy' => 'lastActivity',
                'initialOrderDirection' => true,
                ],
            'dateSubmitted' => [ 'name' => 'dateSubmitted',
                'label' => __('common.dateSubmitted'),
                'value' => 'dateSubmitted',
                ],
            'participants' => [ 'name' => 'participants',
                'label' => __('submissionGroup.assignedSubEditors'),
                'value' => 'participants',
                ],
            ];
        

        return $columnsProperties;
    }

    protected function getUserGroup(int $userGroupId): ?UserGroup
    {
        /** @var UserGroup $userGroup */
        foreach ($this->userGroups as $userGroup) {
            if ($userGroup->getId() === $userGroupId) {
                return $userGroup;
            }
        }
        return null;
    }
}
