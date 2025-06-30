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
use PKP\API\v1\reviewers\suggestions\resources\ReviewerSuggestionResource;
use PKP\db\DAORegistry;
use PKP\decision\DecisionType;
use PKP\plugins\Hook;
use PKP\plugins\PluginRegistry;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\genre\Genre;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\reviewer\suggestion\ReviewerSuggestion;
use PKP\submission\reviewRound\ReviewRound;
use PKP\submission\reviewRound\ReviewRoundDAO;
use PKP\submissionFile\SubmissionFile;
use PKP\user\User;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\relationships\UserUserGroup;
use PKP\userGroup\UserGroup;
use PKP\workflow\WorkflowStageDAO;

class Schema extends \PKP\core\maps\Schema
{
    /** @copydoc \PKP\core\maps\Schema::$collection */
    public Enumerable $collection;

    /** @copydoc \PKP\core\maps\Schema::$schema */
    public string $schema = PKPSchemaService::SCHEMA_SUBMISSION;

    /** @var Enumerable<int,UserGroup> The user groups for this context. */
    public Enumerable $userGroups;

    /** @var array user roles associated with the context, Role::ROLE_ID_ constants  */
    public array $userRoles;

    /** @var Genre[] The file genres in this context. */
    public array $genres;

    /** @var Enumerable Review assignments associated with submissions. */
    public Enumerable $reviewAssignments;

    /** @var Enumerable Stage assignments associated with submissions. */
    public Enumerable $stageAssignments;

    /** @var Enumerable Decisions associated with submissions. */
    public Enumerable $decisions;

    /** @var Enumerable Reviewer Suggestions associated with submissions. */
    public Enumerable $reviewerSuggestions;

    /** Workflow stage files associated with submissions. */
    public Enumerable $submissionStageFiles;
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
            'canCurrentUserChangeMetadata',
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
     * @param Enumerable<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param array $userRoles The roles associated with the current user within the context
     * @param ?Enumerable $reviewAssignments review assignments associated with a submission
     * @param ?Enumerable $stageAssignments stage assignments associated with a submission
     * @param ?Enumerable $decisions decisions associated with a submission
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     * @param ?Enumerable $reviewerSuggestions List of suggested reviewer associated with submission
     */
    public function map(
        Submission $item,
        Enumerable $userGroups,
        array $genres,
        array $userRoles,
        ?Enumerable $reviewAssignments = null,
        ?Enumerable $stageAssignments = null,
        ?Enumerable $decisions = null,
        bool|Collection $anonymizeReviews = false,
        ?Enumerable $reviewerSuggestions = null,
        ?Enumerable $stageFiles = null
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->userRoles = $userRoles;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]));
        $this->decisions = $decisions ?? Repo::decision()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->reviewerSuggestions = $reviewerSuggestions ?? ReviewerSuggestion::withSubmissionIds($item->getId())->get();
        $this->submissionStageFiles = $stageFiles ?? $this->getStageFilesBySubmissions(collect([$item]), [SubmissionFile::SUBMISSION_FILE_COPYEDIT]);
        $this->addAppSpecificData(collect([$item]));

        return $this->mapByProperties($this->getProps(), $item, $anonymizeReviews);
    }

    /**
     * Summarize a submission
     *
     * Includes properties with the apiSummary flag in the submission schema.
     *
     * @param Enumerable<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param ?Enumerable $reviewAssignments review assignments associated with a submission
     * @param ?Enumerable $stageAssignments stage assignments associated with a submission
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     * @param ?Enumerable $reviewerSuggestions List of suggested reviewer associated with submission
     */
    public function summarize(
        Submission $item,
        Enumerable $userGroups,
        array $genres,
        ?Enumerable $reviewAssignments = null,
        ?Enumerable $stageAssignments = null,
        bool|Collection $anonymizeReviews = false,
        ?Enumerable $reviewerSuggestions = null,
        ?Enumerable $stageFiles = null
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]));
        $this->reviewerSuggestions = $reviewerSuggestions ?? ReviewerSuggestion::withSubmissionIds($item->getId())->get();
        $this->submissionStageFiles = $stageFiles ?? $this->getStageFilesBySubmissions(collect([$item]), [SubmissionFile::SUBMISSION_FILE_COPYEDIT]);
        $this->addAppSpecificData(collect([$item]));

        return $this->mapByProperties($this->getSummaryProps(), $item, $anonymizeReviews);
    }

    /**
     * Map a collection of Submissions
     *
     * @see self::map
     *
     * @param Enumerable<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param array $userRoles roles of the current user within the context
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function mapMany(
        Enumerable $collection,
        Enumerable $userGroups,
        array $genres,
        array $userRoles,
        bool|Collection $anonymizeReviews = false
    ): Enumerable {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->userRoles = $userRoles;

        $submissionIds = $collection->keys()->toArray();
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($submissionIds)->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection);
        $this->decisions = Repo::decision()->getCollector()->filterBySubmissionIds($submissionIds)->getMany()->remember();
        $this->submissionStageFiles = $this->getStageFilesBySubmissions($collection, [SubmissionFile::SUBMISSION_FILE_COPYEDIT]);
        $this->addAppSpecificData($collection);

        $associatedReviewAssignments = $this->reviewAssignments->groupBy(fn (ReviewAssignment $reviewAssignment, int $key) =>
            $reviewAssignment->getData('submissionId'));
        $associatedStageAssignments = $this->stageAssignments->groupBy(fn (StageAssignment $stageAssignment, int $key) =>
            $stageAssignment->submissionId);
        $associatedDecisions = $this->decisions->groupBy(
            fn (Decision $decision, int $key) =>
            $decision->getData('submissionId')
        );

        /** @var \Illuminate\Support\LazyCollection $associatedReviewerSuggestions */
        $associatedReviewerSuggestions = ReviewerSuggestion::query()
            ->withSubmissionIds($collection->keys()->toArray())
            ->cursor()
            ->groupBy('submissionId');

        $associatedSubmissionStageFiles = $this->submissionStageFiles->groupBy(
            fn (SubmissionFile $submissionFile, int $key) => $submissionFile->getData('submissionId')
        );

        return $collection->map(
            fn ($item) =>
            $this->map(
                $item,
                $this->userGroups,
                $this->genres,
                $this->userRoles,
                $associatedReviewAssignments->get($item->getId()),
                $associatedStageAssignments->get($item->getId()),
                $associatedDecisions->get($item->getId()),
                $anonymizeReviews,
                $associatedReviewerSuggestions->get($item->getId()),
                $associatedSubmissionStageFiles->get($item->getId())
            )
        );
    }

    /**
     * Summarize a collection of Submissions
     *
     * @see self::summarize
     *
     * @param Enumerable<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     */
    public function summarizeMany(Enumerable $collection, Enumerable $userGroups, array $genres, bool|Collection $anonymizeReviews = false): Enumerable
    {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($collection->keys()->toArray())->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection);
        $this->submissionStageFiles = $this->getStageFilesBySubmissions($collection, [SubmissionFile::SUBMISSION_FILE_COPYEDIT]);
        $this->addAppSpecificData($collection);
        $associatedReviewAssignments = $this->reviewAssignments->groupBy(
            fn (ReviewAssignment $reviewAssignment, int $key) =>
            $reviewAssignment->getData('submissionId')
        );

        $associatedStageAssignment = $this->stageAssignments->groupBy(
            fn (StageAssignment $stageAssignment, int $key) =>
            $stageAssignment->submissionId
        );

        /** @var \Illuminate\Support\LazyCollection $associatedReviewerSuggestions */
        $associatedReviewerSuggestions = ReviewerSuggestion::query()
            ->withSubmissionIds($collection->keys()->toArray())
            ->cursor()
            ->groupBy('submissionId');

        $associatedSubmissionStageFiles = $this->submissionStageFiles->groupBy(
            fn (SubmissionFile $submissionFile, int $key) => $submissionFile->getData('submissionId')
        );

        return $collection->map(
            fn ($item) =>
            $this->summarize(
                $item,
                $this->userGroups,
                $this->genres,
                $associatedReviewAssignments->get($item->getId()),
                $associatedStageAssignment->get($item->getId()),
                $anonymizeReviews,
                $associatedReviewerSuggestions->get($item->getId()),
                $associatedSubmissionStageFiles->get($item->getId())
            )
        );
    }

    /**
     * Map a submission with extra properties for the submissions list
     *
     * @param Enumerable<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param ?Enumerable $reviewAssignments review assignments associated with a submission
     * @param ?Enumerable $stageAssignments stage assignments associated with a submission
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     * @param ?Enumerable<int, SubmissionFile> $stageFiles List of stage files associated with a submission
     */
    public function mapToSubmissionsList(
        Submission $item,
        Enumerable $userGroups,
        array $genres,
        ?Enumerable $reviewAssignments = null,
        ?Enumerable $stageAssignments = null,
        ?Enumerable $decisions = null,
        bool|Collection $anonymizeReviews = false,
        ?Enumerable $stageFiles = null
    ): array {
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->reviewAssignments = $reviewAssignments ?? Repo::reviewAssignment()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->stageAssignments = $stageAssignments ?? $this->getStageAssignmentsBySubmissions(collect([$item]));
        $this->decisions = $decisions ?? Repo::decision()->getCollector()->filterBySubmissionIds([$item->getId()])->getMany()->remember();
        $this->reviewerSuggestions = $reviewerSuggestions ?? ReviewerSuggestion::withSubmissionIds($item->getId())->get();
        $this->submissionStageFiles = $stageFiles ?? $this->getStageFilesBySubmissions(collect([$item]), [SubmissionFile::SUBMISSION_FILE_COPYEDIT]);
        $this->addAppSpecificData(collect([$item]));

        return $this->mapByProperties($this->getSubmissionsListProps(), $item, $anonymizeReviews);
    }

    /**
     * Map a collection of submissions with extra properties for the submissions list
     *
     * @param LazyCollection<int,UserGroup> $userGroups The user groups in this context
     * @param Genre[] $genres The file genres in this context
     * @param array $userRoles The roles associated with the current user
     * @param bool|Collection<int> $anonymizeReviews List of review assignment IDs to anonymize
     *
     *@see self::map
     *
     */
    public function mapManyToSubmissionsList(
        Enumerable $collection,
        Enumerable $userGroups,
        array $genres,
        array $userRoles,
        bool|Collection $anonymizeReviews = false
    ): Enumerable {
        $this->collection = $collection;
        $this->userGroups = $userGroups;
        $this->genres = $genres;
        $this->userRoles = $userRoles;

        $submissionIds = $collection->keys()->toArray();
        $this->reviewAssignments = Repo::reviewAssignment()->getCollector()->filterBySubmissionIds($submissionIds)->getMany()->remember();
        $this->stageAssignments = $this->getStageAssignmentsBySubmissions($collection);
        $this->decisions = Repo::decision()->getCollector()->filterBySubmissionIds($submissionIds)->getMany()->remember();
        $this->submissionStageFiles = $this->getStageFilesBySubmissions($collection, [SubmissionFile::SUBMISSION_FILE_COPYEDIT]);
        $this->addAppSpecificData($collection);

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

        /** @var \Illuminate\Support\LazyCollection $associatedReviewerSuggestions */
        $associatedReviewerSuggestions = ReviewerSuggestion::query()
            ->withSubmissionIds($collection->keys()->toArray())
            ->cursor()
            ->groupBy('submissionId');

        $associatedSubmissionStageFiles = $this->submissionStageFiles->groupBy(
            fn (SubmissionFile $submissionFile, int $key) => $submissionFile->getData('submissionId')
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
                $anonymizeReviews,
                $associatedSubmissionStageFiles->get($item->getId())
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
        $this->reviewerSuggestions = ReviewerSuggestion::query()->withSubmissionIds($item->getId())->get();

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
                ->filterByReviewerIds([$this->request->getUser()->getId()], true)
                ->getMany()
                ->first();
            $anonymize = $currentUserReviewAssignment && $currentUserReviewAssignment->getReviewMethod() === ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS;
        }

        $reviewRounds = $this->getReviewRoundsFromSubmission($submission);
        $currentReviewRound = $reviewRounds->sortKeys()->last(); /** @var ReviewRound|null $currentReviewRound */
        $stages = in_array('stages', $props) ?
            $this->getPropertyStages($this->stageAssignments, $this->reviewAssignments, $submission, $this->submissionStageFiles, $this->decisions ?? null, $currentReviewRound) :
            [];

        foreach ($props as $prop) {
            switch ($prop) {
                case '_href':
                    $output[$prop] = Repo::submission()->getUrlApi($this->context, $submission->getId());
                    break;
                case 'availableEditorialDecisions':
                    $output[$prop] = collect($this->getAvailableEditorialDecisions(
                        $submission->getData('stageId'),
                        $submission
                    ))->map(
                        fn (DecisionType $decisionType) =>
                        [
                            'stageId' => $submission->getData('stageId'),
                            'id' => $decisionType->getDecision(),
                            'label' => $decisionType->getLabel(),
                        ]
                    )->toArray();
                    break;
                case 'canCurrentUserChangeMetadata':
                    // Identify if current user can change metadata. Consider roles in the active stage.
                    $output[$prop] = $this->canChangeMetadata($this->stageAssignments, $submission);
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
                    $output[$prop] = $this->getPropertyReviewAssignments($this->reviewAssignments, $anonymizeReviews, $submission, $stages);
                    break;
                case 'participants':
                    $output[$prop] = $this->getPropertyParticipants($submission);
                    break;
                case 'reviewersNotAssigned':
                    $output[$prop] = $currentReviewRound && $this->reviewAssignments->count() < $this->context->getNumReviewsPerSubmission();
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
                    $output[$prop] = array_values($stages);
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
                case 'reviewerSuggestions':
                    $output[$prop] = $this->getPropertyReviewerSuggestions($this->reviewerSuggestions);
                    break;
                default:
                    $output[$prop] = $submission->getData($prop);
                    break;
            }
        }

        return $output;
    }

    /**
     * Determine whether current user is able to change metadata
     */
    protected function canChangeMetadata(Enumerable $stageAssignments, Submission $submission): bool
    {
        $currentUser = Application::get()->getRequest()->getUser();
        $isAssigned = false;
        $canChangeMetadata = false;

        // Check if stage assignment is associated with the current user and edit metadata flag
        foreach ($stageAssignments as $stageAssignment) {
            if ($stageAssignment->userId === $currentUser->getId()) {
                $isAssigned = true;
                if ($stageAssignment->canChangeMetadata) {
                    $canChangeMetadata = true;
                    break;
                }
            }
        }

        if ($canChangeMetadata) {
            return true;
        }

        // If user is assigned, check editorial global roles, journal admin and managers should have access for editing metadata
        if (!$isAssigned) {
            if (!empty(array_intersect(
                $this->userRoles,
                [Role::ROLE_ID_SITE_ADMIN, Role::ROLE_ID_MANAGER]
            ))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get details about the reviewer suggestions for a submission
     */
    protected function getPropertyReviewerSuggestions(Enumerable $reviewerSuggestions): array
    {
        $reviewerSuggestionProps = collect(
            $this->schemaService
                ->get($this->schema)
                ->properties
                ->reviewerSuggestions
                ->items
                ->properties
        )->keys()->toArray();

        $reviewerSuggestions = collect(
            array_values(
                ReviewerSuggestionResource::collection($reviewerSuggestions)
                    ->toArray(app()->get('request'))
            )
        )->map(
            fn (array $suggestion): array => array_intersect_key(
                $suggestion,
                array_flip($reviewerSuggestionProps)
            )
        )->toArray();

        return $reviewerSuggestions;
    }

    public function summarizeReviewerSuggestion(Enumerable $reviewerSuggestions): array
    {
        return $this->getPropertyReviewerSuggestions($reviewerSuggestions);
    }

    /**
     * Get details about the review assignments for a submission
     */
    protected function getPropertyReviewAssignments(Enumerable $reviewAssignments, bool|Collection $anonymizeReviews = false, Submission $submission, array $stages): array
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();

        $reviews = [];
        foreach ($reviewAssignments as $reviewAssignment) { /** @var \PKP\submission\reviewAssignment\ReviewAssignment $reviewAssignment */
            // skip declined/cancelled assignments if the user lacks permission for this specific stage.
            if (
                !$this->canSeeAllReviewAssignments($reviewAssignment, $stages)
                && ($reviewAssignment->getDeclined() || $reviewAssignment->getCancelled())
            ) {
                continue;
            }

            $dateDue = is_null($reviewAssignment->getDateDue()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateDue()));
            $dateResponseDue = is_null($reviewAssignment->getDateResponseDue()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateResponseDue()));
            $dateConfirmed = is_null($reviewAssignment->getDateConfirmed()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateConfirmed()));
            $dateCompleted = is_null($reviewAssignment->getDateCompleted()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateCompleted()));
            $dateAssigned = is_null($reviewAssignment->getDateAssigned()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateAssigned()));
            $dateConsidered = is_null($reviewAssignment->getDateConsidered()) ? null : date('Y-m-d', strtotime($reviewAssignment->getDateConsidered()));


            // calculate canLoginAs, default to false
            $canLoginAs = false;
            $reviewerId = $reviewAssignment->getReviewerId();
            if ($reviewerId) {
                $canLoginAs = \PKP\security\Validation::canUserLoginAs(
                    $reviewerId,
                    $currentUser->getId()
                );
            }

            // check canGossip (only if this is a reviewer)
            $canGossip = false;
            if ($reviewerId) {
                $canGossip = Repo::user()->canCurrentUserGossip($reviewerId);
            }

            $reviews[] = [
                'id' => (int) $reviewAssignment->getId(),
                'isCurrentUserAssigned' => $currentUser->getId() == (int) $reviewAssignment->getReviewerId(),
                'statusId' => (int) $reviewAssignment->getStatus(),
                'status' => __($reviewAssignment->getStatusKey()),
                'dateDue' => $dateDue,
                'dateResponseDue' => $dateResponseDue,
                'dateConfirmed' => $dateConfirmed,
                'dateCompleted' => $dateCompleted,
                'dateConsidered' => $dateConsidered,
                'dateAssigned' => $dateAssigned,
                'competingInterests' => $reviewAssignment->getCompetingInterests(),
                'round' => (int) $reviewAssignment->getRound(),
                'roundId' => (int) $reviewAssignment->getReviewRoundId(),
                'reviewerRecommendationId' => $reviewAssignment->getReviewerRecommendationId(),
                'dateCancelled' => $reviewAssignment->getData('dateCancelled'),
                'reviewerId' => $anonymizeReviews && $anonymizeReviews->contains($reviewAssignment->getId()) ? null : $reviewAssignment->getReviewerId(),
                'reviewerFullName' => $anonymizeReviews && $anonymizeReviews->contains($reviewAssignment->getId()) ? '' : $reviewAssignment->getData('reviewerFullName'),
                'reviewMethod' => $reviewAssignment->getData('reviewMethod'),
                'canLoginAs' => $canLoginAs,
                'canGossip' => $canGossip,
                'reviewerDisplayInitials' => $anonymizeReviews && $anonymizeReviews->contains($reviewAssignment->getId()) ? '' : Repo::user()->get($reviewAssignment->getReviewerId())->getDisplayInitials(),
                'reviewerHasOrcid' => !($anonymizeReviews && $anonymizeReviews->contains($reviewAssignment->getId())) && !!Repo::user()->get($reviewAssignment->getReviewerId())->getData('orcidIsVerified')
            ];
        }

        return $reviews;
    }

    /**
     * Checks whether the current user can see declined/cancelled review assignments
     * for the stage of the given $reviewAssignment.
     */
    protected function canSeeAllReviewAssignments(ReviewAssignment $reviewAssignment, array $stages): bool
    {
        $stageId = $reviewAssignment->getStageId();

        // if we don't have information for this stage or no roles were assigned, user can't see everything
        if (!isset($stages[$stageId]['currentUserAssignedRoles']) || empty($stages[$stageId]['currentUserAssignedRoles'])) {
            return false;
        }

        $roles = $stages[$stageId]['currentUserAssignedRoles'];

        // only managers, sub-editors, or site admins at THIS stage can see
        // the declined/cancelled assignments for that stage
        if (array_intersect($roles, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR, Role::ROLE_ID_SITE_ADMIN])) {
            return true;
        }

        return false;
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
     * Get a list of participants assigned to this submission
     * and build an array with canLoginAs
     *
     */
    protected function getPropertyParticipants(Submission $submission): array
    {
        $participants = [];

        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();
        $context = $request->getContext();

        if (!$context) {
            // If the context is somehow null, bail out
            return [];
        }

        // collect all users assigned to this submission. this retrieves users across all stages.
        $usersIterator = Repo::user()
            ->getCollector()
            ->filterByContextIds([$context->getId()])
            ->assignedTo($submission->getId())
            ->getMany();

        // build the array for each user
        foreach ($usersIterator as $user) {
            // gather the properties to return
            $userId = $user->getId();
            $fullName = $user->getFullName();

            // get value of canLoginAs
            $canLoginAs = \PKP\security\Validation::canUserLoginAs(
                $userId,
                $currentUser->getId(),
                // passing the submission's contextId null for site-wide check
                $submission->getData('contextId')
            );

            $participants[] = [
                'id' => $userId,
                'fullName' => $fullName,
                'canLoginAs' => $canLoginAs,
            ];
        }

        return $participants;
    }

    /**
     * Get details about a submission's stage(s)
     *
     * @return array
     * [
     *  {
     *  `id` int stage id
     *  `label` string translated stage name
     *  `isActiveStage` boolean whether the stage is active
     *  `editorAssigned` boolean whether the editor is assigned to the submission
     *  `isDecidingEditorAssigned` boolean whether apart from recommend only editor, there is at least one editor without recommend only flag assigned
     *  `isCurrentUserDecidingEditor` boolean whether the current user is assigned as an editor without recommend only flag (and there are recommend only editors assigned)
     *  `currentUserAssignedRoles` array the roles of the current user in the submission per stage, user may be unassigned but have global manager role
     *  `currentUserCanRecommendOnly` whether the current user is an editor with the recommend only flag
     *  `currentUserRecommendation` object includes the recommendation decision of the current user
     *  `uploadedFilesCount` int || null the count of files upload to the stage. A null value indicates that the count was not included
     *  {
     *   `decision` => recommendation decision,
     *   `label` => decision label
     *  },
     *  `recommendations` array shows to the deciding editor all recommendations associated with the submission in the review stages
     *   [
     *    decisionID =>
     *    {
     *     `decision` => recommendation decision,
     *     `label` => decision label
     *    }
     *   ]
     *  }
     * ]
     */
    protected function getPropertyStages(Enumerable $stageAssignments, Enumerable $reviewAssignments, Submission $submission, Enumerable $stageFiles, ?Enumerable $decisions, ?ReviewRound $currentReviewRound): array
    {
        $request = Application::get()->getRequest();
        $currentUser = $request->getUser();

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
                'currentUserAssignedRoles' => [],
            ];

            if ($stageId === WORKFLOW_STAGE_ID_EDITING) {
                $stages[$stageId]['uploadedFilesCount'] = $stageFiles->filter(fn (SubmissionFile $file) => $file->getData('fileStage') == SubmissionFile::SUBMISSION_FILE_COPYEDIT)->count();
            } else {
                // A `null` value is used to indicate that no count data is available.
                // This is also done to ensure that all stage objects has the same properties
                $stages[$stageId]['uploadedFilesCount'] = null;
            }
        }

        $isAssignedInAnyRole = false; // Determine if the current user is assigned to the submission in any role
        $hasDecidingEditor = false;
        $hasRecommendingEditors = false;
        $isCurrentUserDecidingEditor = false;

        // Determine stage assignment related data
        foreach ($stageAssignments as $stageAssignment) {

            // Record recommendations for review stages
            if ($stageAssignment->recommendOnly) {
                if (!$hasRecommendingEditors) {
                    $hasRecommendingEditors = true;
                }
            }

            $userGroup = $stageAssignment->userGroup; /** @var UserGroup $userGroup */

            foreach ($userGroup->userGroupStages as $groupStage) { /** @var UserGroupStage $groupStage */
                // Identify the first user with the editor
                if (
                    !$stages[$groupStage->stageId]['editorAssigned'] &&
                    in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
                ) {
                    $stages[$groupStage->stageId]['editorAssigned'] = true;
                }

                // Identify the first user with the editor role and with a recommend only flag
                if (
                    !$hasDecidingEditor &&
                    in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]) &&
                    !$stageAssignment->recommendOnly
                ) {
                    $hasDecidingEditor = true;
                }

                // Identify properties related to the current user
                if ($stageAssignment->userId !== $currentUser->getId()) {
                    continue;
                }

                // Identify current user roles associated with the assignment, include global roles and roles from other assignments
                if ($roleId = $this->getAssignmentRoles($stageAssignment)) {
                    $stages[$groupStage->stageId]['currentUserAssignedRoles'][] = $roleId;

                    // Check that the user is assigned in any non-revoked role
                    if (!$isAssignedInAnyRole) {
                        $isAssignedInAnyRole = true;
                    }
                }

                // Identify data associated with editorial roles
                if (!in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])) {
                    continue;
                }

                if (!$stageAssignment->recommendOnly) {
                    $isCurrentUserDecidingEditor = true;
                }

                // if the user is assigned several times in the editorial role, and
                // one of the assignments have recommendOnly option set, consider it here
                if (
                    in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]) &&
                    $stageAssignment->recommendOnly
                ) {
                    $stages[$groupStage->stageId]['currentUserCanRecommendOnly'] = true;
                }
            }
        }

        // if the current user is not assigned in any non-revoked role but has a global role as a manager or admin, consider it in the submission
        if (!$isAssignedInAnyRole) {
            $hasCurrentUserReviewAssignment = $this->reviewAssignments->contains(
                fn (ReviewAssignment $reviewAssignment) =>
                    $reviewAssignment->getReviewerId() === $currentUser->getId() &&
                    !$reviewAssignment->getDeclined() &&
                    !$reviewAssignment->getCancelled()
            );
            // when being assigned as reviewer to this submission, don't add global roles
            if (!$hasCurrentUserReviewAssignment) {
                $globalRoles = array_intersect([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN], $this->userRoles);
                if (!empty($globalRoles)) {
                    foreach ($stageIds as $stageId) {
                        $stages[$stageId]['currentUserAssignedRoles'] = $globalRoles;
                        if ($hasRecommendingEditors) {
                            $isCurrentUserDecidingEditor = $stages[$stageId]['isCurrentUserDecidingEditor'] = true;
                        }
                    }
                }
            }
        }

        // Retrieve recommendations for the review stage
        $reviewRecommendations = collect();
        if (
            isset($currentReviewRound) &&
            isset($decisions) && $decisions->isNotEmpty()
        ) {
            foreach ($decisions as $decision) {

                // Get only recommendation decisions
                $decisionType = Repo::decision()->getDecisionType($decision->getData('decision'));
                if (!Repo::decision()->isRecommendation($decisionType->getDecision())) {
                    continue;
                }

                // Get only decisions related to the relevant review round
                if ($currentReviewRound->getId() != $decision->getData('reviewRoundId')) {
                    continue;
                }

                $reviewRecommendations->push($decision);
            }
        }

        if ($reviewRecommendations->isNotEmpty()) {
            // Group recommendations by user ID [userId => $recommendations]
            $recommendationsByUserIds = $reviewRecommendations->groupBy(
                fn (Decision $decision) =>
                $decision->getData('editorId')
            );

            $currentUserRecommendation = null;
            $latestRecommendations = [];

            foreach ($recommendationsByUserIds as $userId => $userRecommendations) {

                // Get the latest recommendation only
                $latestRecommendation = $userRecommendations->sortByDesc(
                    fn (Decision $recommendation) =>
                    strtotime($recommendation->getData('dateDecided'))
                )->first();

                $recommendationData = [
                    'decision' => $latestRecommendation->getData('decision'),
                    'label' => Repo::decision()->getDecisionType($latestRecommendation->getData('decision'))->getRecommendationLabel(),
                ];

                $latestRecommendations[] = $recommendationData;

                if ($userId === $currentUser->getId()) {
                    $currentUserRecommendation = $recommendationData;
                }
            }

            // Set recommendations for the deciding editor
            if ($isCurrentUserDecidingEditor && !empty($latestRecommendations)) {
                $stages[$decision->getData('stageId')]['recommendations'] = $latestRecommendations;
            }

            // Set own recommendations of the current user
            if ($currentUserRecommendation) {
                $stages[$decision->getData('stageId')]['currentUserRecommendation'] = $currentUserRecommendation;
            }
        }

        foreach ($stages as $stageId => $stage) {
            if ($hasRecommendingEditors) {

                // Determine if deciding editor is assigned
                if ($hasDecidingEditor) {
                    $stages[$stageId]['isDecidingEditorAssigned'] = true;
                }

                // We need to expose isCurrentUserDecidingEditor prop only when recommending editor is assigned
                if ($isCurrentUserDecidingEditor) {
                    $stages[$stageId]['isCurrentUserDecidingEditor'] = true;
                }
            }
        }

        return $stages;
    }

    /**
     * @return array Roles associated with the
     */
    protected function getAssignmentRoles(StageAssignment $stageAssignment): ?int
    {
        $userGroup = $stageAssignment->userGroup;
        $userUserGroup = $userGroup->userUserGroups->first(
            fn (UserUserGroup $userUserGroup) =>
            $userUserGroup->userId === $stageAssignment->userId && // Check if user is associated with stage assignment
            (!$userUserGroup->dateEnd || $userUserGroup->dateEnd->gt(now())) &&
            (!$userUserGroup->dateStart || $userUserGroup->dateStart->lte(now()))
        );

        return $userUserGroup ? $userGroup->roleId : null;
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
        foreach ($this->userGroups as $userGroup) {
            if ($userGroup->id === $userGroupId) {
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
        $stageAssignments = StageAssignment::with(['userGroup.userUserGroups', 'userGroup.userGroupStages'])
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

    /**
     * Implement by a child class to get available editorial decisions data for a stage of a submission.
     */
    protected function getAvailableEditorialDecisions(int $stageId, Submission $submission): array
    {
        return [];
    }

    /**
     * Check if a user can make Decisions or Recommendations on a submission's stage
    */
    protected function checkDecisionPermissions(int $stageId, Submission $submission, User $user, int $contextId): array
    {
        /** @var StageAssignment[] $editorsStageAssignments*/
        $editorsStageAssignments = StageAssignment::withSubmissionIds([$submission->getId()])
            ->withStageIds([$stageId])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->withUserId($user->getId())
            ->get();

        $makeRecommendation = $makeDecision = false;
        // if the user is assigned several times in an editorial role, check his/her assignments permissions i.e.
        // if the user is assigned with both possibilities: to only recommend as well as make decision
        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if (!$editorsStageAssignment->recommendOnly) {
                $makeDecision = true;
            } else {
                $makeRecommendation = true;
            }
        }

        // If user is not assigned to the submission,
        // see if the user is manager, and
        // if the group is recommendOnly
        if (!$makeRecommendation && !$makeDecision) {
            $userGroups = Repo::userGroup()->userUserGroups($user->getId(), $contextId);
            foreach ($userGroups as $userGroup) {
                if (in_array($userGroup->roleId, [Role::ROLE_ID_MANAGER, Role::ROLE_ID_SITE_ADMIN])) {
                    if (!$userGroup->recommendOnly) {
                        $makeDecision = true;
                    } else {
                        $makeRecommendation = true;
                    }
                }
            }
        }

        // if the user can make recommendations, check whether there are any decisions that can be made given
        // the stage that we are operating into.
        $isOnlyRecommending = $makeRecommendation && !$makeDecision;

        if ($isOnlyRecommending) {
            if (!empty(Repo::decision()->getDecisionTypesMadeByRecommendingUsers($stageId))) {
                // If there are any, then the user can be considered a decision user.
                $makeDecision = true;
            }
        }

        return [
            'canMakeDecision' => $makeDecision,
            'canMakeRecommendation' => $makeRecommendation,
            'isOnlyRecommending' => $isOnlyRecommending
        ];
    }

    /**
     *
     * @param Enumerable<Submission> $submissions
     * @param array $stageIds The IDs of the stages to limit results to
     *
     * @return LazyCollection<SubmissionFile> The collection of files associated with submissions
     */
    protected function getStageFilesBySubmissions(Enumerable $submissions, array $stageIds): LazyCollection
    {
        $submissionIds = [];
        $submissions->each(function (Submission $submission) use (&$submissionIds) {
            $submissionIds[] = $submission->getId();
        });

        return Repo::submissionFile()
            ->getCollector()
            ->filterBySubmissionIds($submissionIds)
            ->filterByFileStages($stageIds)
            ->getMany();
    }

    /**
     * Implement by a child class to populate app specific data.
     *
     * @param Enumerable<int, Submission> $submissions Submissions, keyed by submission ID.
     */
    protected function addAppSpecificData(Enumerable $submissions): void
    {

    }
}
