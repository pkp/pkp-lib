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
use APP\decision\Decision;
use APP\facades\Repo;
use APP\submission\Submission;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\LazyCollection;
use PKP\config\Config;
use PKP\db\DAORegistry;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
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

    /** @var Enumerable Decisions associated with submissions. */
    public Enumerable $decisions;

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
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection);

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
        ?Enumerable $decisions = null,
        bool|Collection $anonymizeReviews = false
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]));
        $this->decisions = $decisions ?? Repo::decision()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
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

        $submissionIds = $collection->keys()->toArray();
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($submissionIds)->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection);
        $this->decisions = Repo::decision()->getCollector()->filterBySubmissionIds($submissionIds)->getMany()->remember();

        $associatedReviewAssignments = $this->reviewAssignments->groupBy(
            fn (ReviewAssignment $reviewAssignment, int $key) =>
            $reviewAssignment->getData('submissionId')
        );
        $associatedStageAssignments = $this->stageAssignments->groupBy(
            fn (StageAssignment $stageAssignment, int $key) =>
            $stageAssignment->submissionId
        );
        $associatedDecisions = $this->decisions->groupBy(
            fn (Decision $decision, int $key) =>
            $decision->getData('submissionId')
        );

        return $collection->map(
            fn ($item) =>
            $this->mapToSubmissionsList(
                $item,
                $this->userGroups,
                $this->genres,
                $associatedReviewAssignments->get($item->getId()),
                $associatedStageAssignments->get($item->getId()),
                $associatedDecisions->get($item->getId()),
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
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions(collect([$item]));

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
                    $output[$prop] = $this->getPropertyStages($this->stageAssignments, $submission, $this->decisions ?? null, $currentReviewRound);
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
                'competingInterests' => $reviewAssignment->getCompetingInterests(),
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
    protected function getPropertyStages(Enumerable $stageAssignments, Submission $submission, ?Enumerable $decisions, ?ReviewRound $currentReviewRound): array
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        // Replace this part with eager loaded UserGroups
        $userGroupsByStageAssignments = $stageAssignments->mapWithKeys(
            fn (StageAssignment $stageAssignment) =>
            [$stageAssignment->id => $stageAssignment->userGroupId]
        );

        $userGroups = Repo::userGroup()->getCollector()
            ->filterByUserGroupIds($userGroupsByStageAssignments->toArray())
            ->getMany();

        $userGroupsByStageAssignments = $userGroupsByStageAssignments->mapWithKeys(
            fn (int $userGroupId, int $assignmentId) =>
            [$assignmentId => $userGroups->get($userGroupId)]
        );

        // Create stages and fill with predefined data
        $stages = [];
        $stageIds = Application::get()->getApplicationStages();
        $workflowStageDao = DAORegistry::getDAO('WorkflowStageDAO'); /** @var WorkflowStageDAO $workflowStageDao */
        foreach ($stageIds as $stageId) {
            $stages[$stageId] = [
                'id' => (int) $stageId,
                'label' => __($workflowStageDao->getTranslationKeyFromId($stageId)),
                'isActiveStage' => $submission->getData('stageId') == $stageId,

                // values false by default, to be determined later
                'editorAssigned' => false,
                'isDecidingEditorAssigned' => false,
                'isCurrentUserDecidingEditor' => false,
            ];
        }

        $recommendations = [];

        // Determine stage assignment related data
        foreach ($stageAssignments as $stageAssignment) {
            $userGroup = $userGroupsByStageAssignments->get($stageAssignment->id); /** @var UserGroup $userGroup */

            foreach ($stageAssignment->userGroupStages as $groupStage) {
                // Identify the first user with the editor
                if (
                    !$stages[$groupStage->stageId]['editorAssigned'] &&
                    in_array(
                        $userGroup->getRoleId(),
                        [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]
                    )
                ) {
                    $editorAssigned = $stages[$groupStage->stageId]['editorAssigned'] = true;
                }

                // Identify the first user with the editor role and without recommend only flag
                if (
                    !$stages[$groupStage->stageId]['isDecidingEditorAssigned'] &&
                    isset($editorAssigned) &&
                    !$stageAssignment->recommendOnly) {
                    $isDecidingEditorAssigned = $stages[$groupStage->stageId]['isDecidingEditorAssigned'] = true;
                }

                // Record recommendations for review stages
                if (
                    $stageAssignment->recommendOnly &&
                    isset($currentReviewRound) &&
                    isset($decisions) && $decisions->isNotEmpty()
                ) {
                    foreach ($decisions as $decision) {
                        if ($currentReviewRound->getId() != $decision->getData('reviewRoundId')) {
                            continue;
                        }

                        $decisionType = Repo::decision()->getDecisionType($decision->getData('decision'));
                        $recommendations[$decision->getId()] = [
                            'decision' => $decision->getData('decision'),
                            'label' => $decisionType->getLabel(),
                            'stageId' => $decision->getData('stageId'),
                        ];
                    }
                }

                // Identify properties related to the current user
                if ($stageAssignment->userId !== $currentUser->getId()) {
                    continue;
                }

                $stages[$groupStage->stageId]['currentUserAssignedRoles'] = [
                    $userGroup->getRoleId(),
                ];

                if (isset($isDecidingEditorAssigned)) {
                    $stages[$groupStage->stageId]['isCurrentUserDecidingEditor'] = true;
                }

                // Identify if the current user gave recommendation
                if (
                    isset($editorAssigned) && // this user is assigned as an editor
                    !isset($isDecidingEditorAssigned) && // this user only can give recommendations, isn't a deciding editor
                    in_array($groupStage->stageId, [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW]) &&
                    isset($decisions) && $decisions->isNotEmpty() // only for submissions list
                ) {
                    foreach ($decisions as $decision) {
                        if (isset($stages[$groupStage->stageId]['currentUserRecommendation'])) {
                            break; // Decision is already recorded, skip
                        }

                        if ($decision->getData('editorId') != $currentUser->getId()) {
                            continue;
                        }

                        if (!in_array($decision->getData('stageId'), [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW])) {
                            continue;
                        }

                        if ($currentReviewRound->getId() != $decision->getData('reviewRoundId')) {
                            continue;
                        }

                        $decision = $decision->getData('decision');
                        $decisionType = Repo::decision()->getDecisionType($decision);
                        $stages[$groupStage->stageId]['currentUserRecommendation'] = [
                            'decision' => $decision,
                            'label' => $decisionType->getLabel(),
                        ];
                    }
                }

                // if the user is assigned several times in the editorial role, and
                // one of the assignments have recommendOnly option set, consider it here
                if (isset($editorAssigned) && $stageAssignment->recommendOnly) {
                    $stages[$groupStage->stageId]['currentUserCanRecommendOnly'] = true;
                }
            }
        }

        // Set recommendation if current user is a deciding editor
        foreach ($stages as $stageId => $stage) {
            if (empty($recommendations)) {
                break;
            }

            if (!in_array($stageId, [WORKFLOW_STAGE_ID_EXTERNAL_REVIEW, WORKFLOW_STAGE_ID_INTERNAL_REVIEW])) {
                continue;
            }

            if (!$stage['isCurrentUserDecidingEditor']) {
                continue;
            }

            foreach ($recommendations as $recommendationId => $recommendation) {
                $stages[$recommendation['stageId']]['recommendations'][$recommendationId] = [
                    'decision' => $recommendation['decision'],
                    'label' => $recommendation['label'],
                ];
            }
        }

        // FIXME pkp/pkp-lib#7495 Backward compatibility only, remove before 3.5 release
        if (!Config::getVar('features', 'enable_new_submission_listing')) {
            $openPerStage = Repo::query()->countOpenPerStage($submission->getId(), [$request->getUser()->getId()]);
            foreach ($stageIds as $stageId) {
                $stages[$stageId]['openQueryCount'] = $openPerStage[$stageId];

                // Stage-specific statuses
                switch ($stageId) {
                    case WORKFLOW_STAGE_ID_SUBMISSION:
                        $assignedEditors = $stages[$stageId]['editorAssigned'];
                        if (!$assignedEditors) {
                            $stages[$stageId]['statusId'] = Repo::submission()::STAGE_STATUS_SUBMISSION_UNASSIGNED;
                            $stages[$stageId]['status'] = __('submissions.queuedUnassigned');
                        }

                        // Submission stage never has revisions
                        $stages[$stageId]['files'] = [
                            'count' => 0,
                        ];
                        break;

                    case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
                    case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
                        $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO'); /** @var ReviewRoundDAO $reviewRoundDao */
                        $reviewRound = $reviewRoundDao->getLastReviewRoundBySubmissionId($submission->getId(), $stageId);
                        if ($reviewRound) {
                            $stages[$stageId]['statusId'] = $reviewRound->determineStatus();
                            $stages[$stageId]['status'] = __($reviewRound->getStatusKey());

                            // Revision files in this round.
                            $stages[$stageId]['files'] = [
                                'count' => Repo::submissionFile()->getCollector()
                                    ->filterBySubmissionIds([$submission->getId()])
                                    ->filterByFileStages([SubmissionFile::SUBMISSION_FILE_REVIEW_REVISION])
                                    ->filterByReviewRoundIds([$reviewRound->getId()])
                                    ->getCount()
                            ];
                        } else {
                            // workaround for pkp/pkp-lib#4231, pending formal data model
                            $stages[$stageId]['files'] = [
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
                        $stages[$stageId]['files'] = [
                            'count' => Repo::submissionFile()->getCollector()
                                ->filterBySubmissionIds([$submission->getId()])
                                ->filterByFileStages($fileStages)
                                ->getCount()
                        ];
                        break;
                }
            }
        }

        return array_values($stages);
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
        $stageAssignments = StageAssignment::with(['userGroupStages'])
            ->withSubmissionIds($submissionIds)
            ->withRoleIds(empty($roleIds) ? null : $roleIds)
            ->lazy();

        return $stageAssignments;
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
