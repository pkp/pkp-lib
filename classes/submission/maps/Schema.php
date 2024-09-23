<?php
/**
 * @file classes/submission/maps/Schema.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Schema
 *
 * @brief Map submissions to the properties defined in the submission schema
 */

namespace PKP\submission\maps;

use APP\core\Application;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\query\Query;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\Genre;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\userGroup\UserGroup;
use PKP\workflow\WorkflowStageDAO;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION;

    /** @var LazyCollection<int,UserGroup> The user groups for this context. */
    public LazyCollection $userGroups;

    /** @var Genre[] The file genres in this context. */
    public array $genres;

    /** @var Enumerable Review assignments associated with submissions. */
    public Enumerable $reviewAssignments;

    /** @var Enumerable Stage assignments associated with submissions. */
    public Enumerable $stageAssignments;

    /**
     * Get extra property names used in the submissions list
     *
     * @hook Submission::getSubmissionsListProps [[&$props]]
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
            'editorAssigned',
            'id',
            'lastModified',
            'publications',
            'recommendationsIn',
            'reviewAssignments',
            'reviewersNotAssigned',
            'reviewRounds',
            'revisionsRequested',
            'revisionsSubmitted',
            'stageId',
            'stageName',
            'stages',
            'status',
            'statusLabel',
            'submissionProgress',
            'urlAuthorWorkflow',
            'urlEditorialWorkflow',
            'urlWorkflow',
            'urlPublished',
        ];

        $props = array_merge($props, $this->appSpecificProps());

        Hook::call('Submission::getSubmissionsListProps', [&$props]);

        return $props;
    }

    /**
     * Map a submission
     *
     * Includes all properties in the submission schema.
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param ?Enumerable $reviewAssignments review assignments associated with a submission
     * @param ?Enumerable $stageAssignments stage assignments associated with a submission
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function map(
        Submission $item,
        LazyCollection $userGroups,
        array $genres,
        ?Enumerable $reviewAssignments = null,
        ?Enumerable $stageAssignments = null,
        bool|Collection $anonymizeReviews = false
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

        return $this->mapByProperties($this->getProps(), $item, $anonymizeReviews);
    }

    /**
     * Summarize a submission
     *
     * Includes properties with the apiSummary flag in the submission schema.
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param ?Enumerable $reviewAssignments review assignments associated with a submission
     * @param ?Enumerable $stageAssignments stage assignments associated with a submission
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function summarize(
        Submission $item,
        LazyCollection $userGroups,
        array $genres,
        ?Enumerable $reviewAssignments = null,
        ?Enumerable $stageAssignments = null,
        bool|Collection $anonymizeReviews = false,
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

        return $this->mapByProperties($this->getSummaryProps(), $item, $anonymizeReviews);
    }

    /**
     * Map a collection of Submissions
     *
     * @see self::map
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function mapMany(Enumerable $collection, LazyCollection $userGroups, array $genres, bool|Collection $anonymizeReviews = false): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($collection->keys()->toArray())->getMany()->remember();

        $associatedReviewAssignments = $this->reviewAssignments->groupBy(fn (ReviewAssignment $reviewAssignment, int $key) =>
            $reviewAssignment->getData('submissionId'));
        $associatedStageAssignments = $this->stageAssignments->groupBy(fn (StageAssignment $stageAssignment, int $key) =>
            $stageAssignment->submissionId);

        return $collection->map(
            fn ($item) =>
            $this->map(
                $item,
                $this->userGroups,
                $this->genres,
                $associatedReviewAssignments->get($item->getId()),
                $associatedStageAssignments->get($item->getId()),
                $anonymizeReviews
            )
        );
    }

    /**
     * Summarize a collection of Submissions
     *
     * @see self::summarize
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function summarizeMany(Enumerable $collection, LazyCollection $userGroups, array $genres, bool|Collection $anonymizeReviews = false): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($collection->keys()->toArray())->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

        $associatedReviewAssignments = $this->reviewAssignments->groupBy(
            fn (ReviewAssignment $reviewAssignment, int $key) =>
            $reviewAssignment->getData('submissionId')
        );
        $associatedStageAssignment = $this->stageAssignments->groupBy(
            fn (StageAssignment $stageAssignment, int $key) =>
            $stageAssignment->submissionId
        );

        return $collection->map(
            fn ($item) =>
            $this->summarize(
                $item,
                $this->userGroups,
                $this->genres,
                $associatedReviewAssignments->get($item->getId()),
                $associatedStageAssignment->get($item->getId()),
                $anonymizeReviews
            )
        );
    }

    /**
     * Map a submission with extra properties for the submissions list
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param ?Enumerable $reviewAssignments review assignments associated with a submission
     * @param ?Enumerable $stageAssignments stage assignments associated with a submission
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function mapToSubmissionsList(
        Submission $item,
        LazyCollection $userGroups,
        array $genres,
        ?Enumerable $reviewAssignments = null,
        ?Enumerable $stageAssignments = null,
        bool|Collection $anonymizeReviews = false
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);
        return $this->mapByProperties($this->getSubmissionsListProps(), $item, $anonymizeReviews);
    }

    /**
     * Map a collection of submissions with extra properties for the submissions list
     *
     * @see self::map
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function mapManyToSubmissionsList(
        Enumerable $collection,
        LazyCollection $userGroups,
        array $genres,
        bool|Collection $anonymizeReviews = false
    ): Enumerable {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($collection->keys()->toArray())->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

        $associatedReviewAssignments = $this->reviewAssignments->groupBy(
            fn (ReviewAssignment $reviewAssignment, int $key) =>
            $reviewAssignment->getData('submissionId')
        );
        $associatedStageAssignment = $this->stageAssignments->groupBy(
            fn (StageAssignment $stageAssignment, int $key) =>
            $stageAssignment->submissionId
        );

        return $collection->map(
            fn ($item) =>
            $this->mapToSubmissionsList(
                $item,
                $this->userGroups,
                $this->genres,
                $associatedReviewAssignments->get($item->getId()),
                $associatedStageAssignment->get($item->getId()),
                $anonymizeReviews
            )
        );
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

        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions(collect([$item]), [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);

        return $this->mapByProperties($props, $item);
    }

    /**
     * Map schema properties of a Submission to an assoc array
     *
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    protected function mapByProperties(array $props, Submission $submission, bool|Collection $anonymizeReviews = false): array
    {
        $output = [];

        if (in_array('publications', $props)) {
            $currentUserReviewAssignment = Repo::reviewAssignment()->getCollector()
                ->filterBySubmissionIds([$submission->getId()])
                ->filterByReviewerIds([$this->request->getUser()->getId()])
                ->filterByLastReviewRound(true)
                ->getMany()
                ->first();
            $anonymize = $currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;
        }

        $reviewRounds = $this->getReviewRoundsFromSubmission($submission);
        $currentReviewRound = $reviewRounds->sortKeys()->last(); /** @var ReviewRound|null $currentReviewRound */

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = Repo::submission()->getUrlApi($this->context, $submission->getId());
                    break;
                case 'editorAssigned':
                    $output[$prop] = $this->getPropertyStageAssignments($this->stageAssignments);
                    break;
                case 'metadataLocales':
                    $output[$prop] = collect($this->context->getSupportedSubmissionMetadataLocaleNames() + $submission->getPublicationLanguageNames())
                        ->sortKeys()
                        ->toArray();
                    break;
                case 'publications':
                    $output[$prop] = Repo::publication()->getSchemaMap($submission, $this->userGroups, $this->genres)
                        ->summarizeMany($submission->getData('publications'), $anonymize)->values();
                    break;
                case 'recommendationsIn':
                    $output[$prop] = $currentReviewRound ? $this->areRecommendationsIn($currentReviewRound, $this->stageAssignments) : null;
                    break;
                case 'reviewAssignments':
                    $output[$prop] = $this->getPropertyReviewAssignments($this->reviewAssignments, $anonymizeReviews);
                    break;
                case 'reviewersNotAssigned':
                    $output[$prop] = $currentReviewRound && $this->reviewAssignments->count() >= intval($this->context->getData('numReviewersPerSubmission'));
                    break;
                case 'reviewRounds':
                    $output[$prop] = $this->getPropertyReviewRounds($reviewRounds);
                    break;
                case 'revisionsRequested':
                    $output[$prop] = $currentReviewRound && $currentReviewRound->getData('status') == ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED;
                    break;
                case 'revisionsSubmitted':
                    $output[$prop] = $currentReviewRound && $currentReviewRound->getData('status') == ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED;
                    break;
                case 'stageName':
                    $output[$prop] = __(Application::get()->getWorkflowStageName($submission->getData('stageId')));
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
    protected function getPropertyReviewAssignments(Enumerable $reviewAssignments, bool|Collection $anonymizeReviews = false): array
    {
        $reviews = [];
        foreach ($reviewAssignments as $reviewAssignment) {
            // @todo for now, only show reviews that haven't been
            // declined or cancelled
            if ($reviewAssignment->getDeclined() || $reviewAssignment->getCancelled()) {
                continue;
            }

            $request = Application::get()->getRequest();
            $currentUser = $request->getUser();
            $dateDue = is_null($reviewAssignment->getDateDue()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateDue()));
            $dateResponseDue = is_null($reviewAssignment->getDateResponseDue()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateResponseDue()));
            $dateConfirmed = is_null($reviewAssignment->getDateConfirmed()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateConfirmed()));
            $dateCompleted = is_null($reviewAssignment->getDateCompleted()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateCompleted()));
            $dateAssigned = is_null($reviewAssignment->getDateAssigned()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateAssigned()));

            $reviews[] = [
                'id' => (int) $reviewAssignment->getId(),
                'isCurrentUserAssigned' => $currentUser->getId() == (int) $reviewAssignment->getReviewerId(),
                'statusId' => (int) $reviewAssignment->getStatus(),
                'status' => __($reviewAssignment->getStatusKey()),
                'dateDue' => $dateDue,
                'dateResponseDue' => $dateResponseDue,
                'dateConfirmed' => $dateConfirmed,
                'dateCompleted' => $dateCompleted,
                'dateAssigned' => $dateAssigned,
                'round' => (int) $reviewAssignment->getRound(),
                'roundId' => (int) $reviewAssignment->getReviewRoundId(),
                'recommendation' => $reviewAssignment->getRecommendation(),
                'dateCancelled' => $reviewAssignment->getData('dateCancelled'),
                'reviewerId' => $anonymizeReviews && $anonymizeReviews->contains($reviewAssignment->getId()) ? null : $reviewAssignment->getReviewerId(),
                'reviewerFullName' => $anonymizeReviews && $anonymizeReviews->contains($reviewAssignment->getId()) ? '' : $reviewAssignment->getData('reviewerFullName'),
                'reviewMethod' => $reviewAssignment->getData('reviewMethod')
            ];
        }

        return $reviews;
    }

    /**
     * Get details about the review rounds for a submission
     *
     * @param Collection<ReviewRound> $reviewRounds
     */
    protected function getPropertyReviewRounds(Collection $reviewRounds): array
    {
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

        $openPerStage = Repo::query()->countOpenPerStage($submission->getId(), [$request->getUser()->getId()]);

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
                // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
                $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                    ->withUserId($currentUser->getId() ?? 0)
                    ->get();

                foreach ($stageAssignments as $stageAssignment) {
                    $userGroup = $this->getUserGroup($stageAssignment->userGroupId);
                    if ($userGroup) {
                        $currentUserAssignedRoles[] = $userGroup->getRoleId();
                    }
                }

                // Replaces StageAssignmentDAO::getBySubmissionAndUserIdAndStageId
                $stageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                    ->withUserId($currentUser->getId())
                    ->withStageIds([$stageId])
                    ->get();

                foreach ($stageAssignments as $stageAssignment) {
                    $userGroup = Repo::userGroup()->get($stageAssignment->userGroupId);
                    $currentUserAssignedRoles[] = (int) $userGroup->getRoleId();
                }
            }
            $stage['currentUserAssignedRoles'] = array_values(array_unique($currentUserAssignedRoles));

            // Stage-specific statuses
            switch ($stageId) {
                case WORKFLOW_STAGE_ID_SUBMISSION:
                    // Replaces StageAssignmentDAO::editorAssignedToStage
                    $assignedEditors = StageAssignment::withSubmissionIds([$submission->getId()])
                        ->withStageIds([$stageId])
                        ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                        ->exists();

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

                        // See if the  current user can only recommend:
                        $user = $request->getUser();

                        // Replaces StageAssignmentDAO::getEditorsAssignedToStage
                        $editorsStageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
                            ->withStageIds([$stageId])
                            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                            ->get();

                        // if the user is assigned several times in the editorial role, and
                        // one of the assignments have recommendOnly option set, consider it here
                        $stage['currentUserCanRecommendOnly'] = false;
                        foreach ($editorsStageAssignments as $editorsStageAssignment) {
                            if ($editorsStageAssignment->userId == $user->getId() && $editorsStageAssignment->recommendOnly) {
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
     * Check if deciding editors are assigned to the submission
     *
     * @param Enumerable<StageAssignment> $stageAssignments
     */
    protected function getPropertyStageAssignments(Enumerable $stageAssignments): bool
    {
        return $stageAssignments->isNotEmpty() && $stageAssignments->contains(
            fn (StageAssignment $stageAssignment) =>
            !$stageAssignment->recommendOnly
        );
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

    /**
     *
     * @param Enumerable<Submission> $submissions
     *
     * @return LazyCollection<StageAssignment> The collection of stage assignments associated with submissions
     */
    protected function getStageAssignmentsBySubmissions(Enumerable $submissions, array $roleIds = []): LazyCollection
    {
        $submissionIds = $submissions->map(fn (Submission $submission) => $submission->getId())->toArray();
        return StageAssignment::withSubmissionIds($submissionIds)
            ->withRoleIds($roleIds)
            ->lazy();
    }

    /**
     * @return Collection<ReviewRound> [round => ReviewRound] sorted by the round number
     */
    protected function getReviewRoundsFromSubmission(Submission $submission): Collection
    {
        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */

        return collect($reviewRoundDao->getBySubmissionId($submission->getId())->toIterator())
            ->keyBy(fn (ReviewRound $reviewRound) => $reviewRound->getData('round'));
    }

    /**
     * @return ?bool if there is one or more recommending editors assigned and all recommendations are given return true,
     * otherwise returns false (recommendations are not yet given) or null (no recommending editors assigned)
     */
    protected function areRecommendationsIn(ReviewRound $currentReviewRound, Enumerable $stageAssignments): ?bool
    {
        $hasDecidingEditors = $stageAssignments->first(fn (StageAssignment $stageAssignment) => $stageAssignment->recommendOnly);

        return $hasDecidingEditors ? $currentReviewRound->getData('status') == ReviewRound::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY : null;
    }

    /**
     * Implement by a child class to add application-specific props to a submission
     */
    protected function appSpecificProps(): array
    {
        return [];
    }
}
