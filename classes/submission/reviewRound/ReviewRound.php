<?php

namespace PKP\submission\reviewRound;

use APP\decision\Decision;
use APP\facades\Repo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use PKP\core\traits\ModelWithSettings;
use PKP\security\Role;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use Illuminate\Database\Eloquent\Model;
use PKP\submission\reviewRound\authorResponse\AuthorResponse;
use Illuminate\Database\Query\Builder;

/**
 * ReviewRound Model
 * // the scope methods
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withSubmissionIds(array $submissionIds) Scope to filter by submission id
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withPublicationIds(array $publicationIds) Scope to filter by publication id
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withStageId(int $stageId) Scope to filter by stage id
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withRound(int $round) Scope to filter by round number
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withStatus(int $status) Scope to filter by status
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withSubmissionFileId(int $submissionFileId) Scope to filter by submission file id
 * @method static \Illuminate\Database\Eloquent\Builder|ReviewRound withContextId(int $contextId) Scope to filter by context id
 * @property int $submissionId
 * @property int $publicationId
 * @property int $stageId
 * @property int $round
 * @property int $reviewRevision
 * @property int $status
 * @property-read int $id
 * @property bool $isAuthorResponseRequested
 */
class ReviewRound extends Model
{
    use ModelWithSettings;

    protected $table = 'review_rounds';
    protected $primaryKey = 'review_round_id';
    public $timestamps = false;

    // The first four statuses are set explicitly by Decisions, which override
    // the current status.
    public const REVIEW_ROUND_STATUS_REVISIONS_REQUESTED = 1;
    public const REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW = 2;
    public const REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL = 3;
    public const REVIEW_ROUND_STATUS_ACCEPTED = 4;
    public const REVIEW_ROUND_STATUS_DECLINED = 5;

    // The following statuses are calculated based on the statuses of ReviewAssignments
    // in this round.
    public const REVIEW_ROUND_STATUS_PENDING_REVIEWERS = 6; // No reviewers have been assigned
    public const REVIEW_ROUND_STATUS_PENDING_REVIEWS = 7; // Waiting for reviews to be submitted by reviewers
    public const REVIEW_ROUND_STATUS_REVIEWS_READY = 8; // One or more reviews is ready for an editor to view
    public const REVIEW_ROUND_STATUS_REVIEWS_COMPLETED = 9; // All assigned reviews have been confirmed by an editor
    public const REVIEW_ROUND_STATUS_REVIEWS_OVERDUE = 10; // One or more reviews is overdue
    // The following status is calculated when the round is in ReviewRound::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED and
    // at least one revision file has been uploaded.
    public const REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED = 11;

    // The following statuses are calculated based on the statuses of recommendOnly EditorAssignments
    // and their decisions in this round.
    public const REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS = 12; // Waiting for recommendations to be submitted by recommendOnly editors
    public const REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY = 13; // One or more recommendations are ready for an editor to view
    public const REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED = 14; // All assigned recommendOnly editors have made a recommendation

    // The following status is calculated when the round is in ReviewRound::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW and
    // at least one revision file has been uploaded.
    public const REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED = 15;

    // The following status is set when a submission return back from copyediting stage to last review round again
    public const REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW = 16;

    protected $fillable = [
        'submissionId',
        'publicationId',
        'stageId',
        'round',
        'reviewRevision',
        'status',
    ];

    protected $casts = [
        'submission_id' => 'integer',
        'publication_id' => 'integer',
        'stage_id' => 'integer',
        'round' => 'integer',
        'review_revision' => 'integer',
        'status' => 'integer',
        'is_author_response_requested' => 'bool',
    ];

    /** @copydoc */
    public function getSettingsTable(): string
    {
        return 'review_round_settings';
    }

    /** @copydoc */
    public static function getSchemaName(): ?string
    {
        return PKPSchemaService::SCHEMA_REVIEW_ROUND;
    }


    /**
     * Get the author response associated with this review round, if any.
     */
    public function roundAuthorResponse(): HasOne
    {
        return $this->hasOne(AuthorResponse::class, 'review_round_id', 'review_round_id');
    }

    /**
     * Calculate the status of this review round.
     *
     * If the round is in revisions, it will search for revision files and set
     * the status accordingly. If the round has not reached a revision status
     * yet, it will determine the status based on the statuses of the round's
     * ReviewAssignments.
     *
     */
    public function determineStatus(): int
    {
        // If revisions have been requested, check to see if any have been
        // submitted
        if ($this->status == self::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED || $this->status == self::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED) {
            // get editor decisions
            $decisionToCheck = $this->stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW ? Decision::PENDING_REVISIONS : Decision::PENDING_REVISIONS_INTERNAL;
            $pendingRevisionDecision = Repo::decision()->getActivePendingRevisionsDecision($this->submissionId, $this->stageId, $decisionToCheck);

            if ($pendingRevisionDecision) {
                if (Repo::decision()->revisionsUploadedSinceDecision($pendingRevisionDecision, $this->submissionId)) {
                    return self::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED;
                }
            }
            return self::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED;
        }


        // If revisions have been requested for re-submission, check to see if any have been
        // submitted
        if ($this->status == self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW || $this->status == self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED) {
            // get editor decisions
            $decisionToCheck = $this->stageId === WORKFLOW_STAGE_ID_EXTERNAL_REVIEW ? Decision::RESUBMIT : Decision::RESUBMIT_INTERNAL;

            $pendingRevisionDecision = Repo::decision()->getActivePendingRevisionsDecision($this->submissionId, $this->stageId, $decisionToCheck);

            if ($pendingRevisionDecision) {
                if (Repo::decision()->revisionsUploadedSinceDecision($pendingRevisionDecision, $this->submissionId)) {
                    return self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED;
                }
            }
            return self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW;
        }

        $statusFinished = in_array(
            $this->status,
            [
                self::REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL,
                self::REVIEW_ROUND_STATUS_ACCEPTED,
                self::REVIEW_ROUND_STATUS_DECLINED
            ]
        );
        if ($statusFinished) {
            return $this->status;
        }

        // Determine the round status by looking at the recommendOnly editor assignment statuses
        $pendingRecommendations = false;
        $recommendationsFinished = true;
        $recommendationsReady = false;

        // Replaces StageAssignmentDAO::getEditorsAssignedToStage
        $editorsStageAssignments = StageAssignment::withSubmissionIds([$this->submissionId])
            ->withStageIds([$this->stageId])
            ->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR])
            ->get();

        foreach ($editorsStageAssignments as $editorsStageAssignment) {
            if ($editorsStageAssignment->recommendOnly) {
                $pendingRecommendations = true;
                // Get recommendation from the assigned recommendOnly editor
                $decisions = Repo::decision()->getCollector()
                    ->filterBySubmissionIds([$this->submissionId])
                    ->filterByStageIds([$this->stageId])
                    ->filterByReviewRoundIds([$this->id])
                    ->filterByEditorIds([$editorsStageAssignment->userId])
                    ->getCount();

                if (!$decisions) {
                    $recommendationsFinished = false;
                } else {
                    $recommendationsReady = true;
                }
            }
        }
        if ($pendingRecommendations) {
            if ($recommendationsFinished) {
                return self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED;
            } elseif ($recommendationsReady) {
                return self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY;
            }
        }

        // Determine the round status by looking at the assignment statuses
        $anyOverdueReview = false;
        $anyIncompletedReview = false;
        $anyUnreadReview = false;
        $reviewAssignments = Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$this->id])
            ->getMany();

        foreach ($reviewAssignments as $reviewAssignment) {
            assert($reviewAssignment instanceof ReviewAssignment);

            $assignmentStatus = $reviewAssignment->getStatus();

            switch ($assignmentStatus) {
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_DECLINED:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_CANCELLED:
                    break;

                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RESPONSE_OVERDUE:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REVIEW_OVERDUE:
                    $anyOverdueReview = true;
                    break;

                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_AWAITING_RESPONSE:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_REQUEST_RESEND:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_ACCEPTED:
                    $anyIncompletedReview = true;
                    break;

                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_RECEIVED:
                case ReviewAssignment::REVIEW_ASSIGNMENT_STATUS_VIEWED:
                    $anyUnreadReview = true;
                    break;
            }
        }

        // Find the correct review round status based on the state of
        // the current review assignments. The check order matters: the
        // first conditions override the others.
        if ($reviewAssignments->isEmpty()) {
            return self::REVIEW_ROUND_STATUS_PENDING_REVIEWERS;
        } elseif ($anyOverdueReview) {
            return self::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE;
        } elseif ($anyUnreadReview) {
            return self::REVIEW_ROUND_STATUS_REVIEWS_READY;
        } elseif ($anyIncompletedReview) {
            return self::REVIEW_ROUND_STATUS_PENDING_REVIEWS;
        } elseif ($pendingRecommendations) {
            return self::REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS;
        }

        // The submission back form copy editing stage to last review round
        if ($this->status == self::REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW) {
            return self::REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW;
        }

        return self::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED;
    }

    /**
     * Get locale key associated with current status
     *
     * @param bool $isAuthor True iff the status is to be shown to the author (slightly tweaked phrasing)
     *
     */
    public function getStatusKey(bool $isAuthor = false): ?string
    {
        return match ($this->determineStatus()) {
            self::REVIEW_ROUND_STATUS_REVISIONS_REQUESTED => 'editor.submission.roundStatus.revisionsRequested',
            self::REVIEW_ROUND_STATUS_REVISIONS_SUBMITTED => 'editor.submission.roundStatus.revisionsSubmitted',
            self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW => 'editor.submission.roundStatus.resubmitForReview',
            self::REVIEW_ROUND_STATUS_RESUBMIT_FOR_REVIEW_SUBMITTED => 'editor.submission.roundStatus.submissionResubmitted',
            self::REVIEW_ROUND_STATUS_SENT_TO_EXTERNAL => 'editor.submission.roundStatus.sentToExternal',
            self::REVIEW_ROUND_STATUS_ACCEPTED => 'editor.submission.roundStatus.accepted',
            self::REVIEW_ROUND_STATUS_DECLINED => 'editor.submission.roundStatus.declined',
            self::REVIEW_ROUND_STATUS_PENDING_REVIEWERS => 'editor.submission.roundStatus.pendingReviewers',
            self::REVIEW_ROUND_STATUS_PENDING_REVIEWS => 'editor.submission.roundStatus.pendingReviews',
            self::REVIEW_ROUND_STATUS_REVIEWS_READY => $isAuthor ? 'author.submission.roundStatus.reviewsReady' : 'editor.submission.roundStatus.reviewsReady',
            self::REVIEW_ROUND_STATUS_REVIEWS_COMPLETED => 'editor.submission.roundStatus.reviewsCompleted',
            self::REVIEW_ROUND_STATUS_REVIEWS_OVERDUE => $isAuthor ? 'author.submission.roundStatus.reviewOverdue' : 'editor.submission.roundStatus.reviewOverdue',
            self::REVIEW_ROUND_STATUS_PENDING_RECOMMENDATIONS => 'editor.submission.roundStatus.pendingRecommendations',
            self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_READY => 'editor.submission.roundStatus.recommendationsReady',
            self::REVIEW_ROUND_STATUS_RECOMMENDATIONS_COMPLETED => 'editor.submission.roundStatus.recommendationsCompleted',
            self::REVIEW_ROUND_STATUS_RETURNED_TO_REVIEW => 'editor.submission.roundStatus.returnedToReview',
            default => null,
        };
    }

    /**
     * Update the review round status.
     *
     * @param ?int $status Optionally pass a ReviewRound::REVIEW_ROUND_STATUS_... to set a
     *  specific status. If not included, will determine the appropriate status
     *  based on ReviewRound::determineStatus().
     */
    public function updateStatus(?int $status = null): void
    {
        $currentStatus = $this->status;

        if ($status === null) {
            $status = $this->determineStatus();
        }

        // Avoid unnecessary database access
        if ($status !== $currentStatus) {
            $this->update(['status' => $status]);
        }
    }

    /**
     * Scope a query to only include review rounds with given submission IDs.
     */
    public function scopeWithSubmissionIds($query, array $submissionIds)
    {
        return $query->whereIn('submission_id', $submissionIds);
    }

    // scope to fetch by  publication IDs
    public function scopeWithPublicationIds($query, array $publicationIds)
    {
        return $query->whereIn('publication_id', $publicationIds);
    }

    /**
     * Scope query to only include review rounds with given stage id.
     */
    public function scopeWithStageId($query, int $stageId)
    {
        return $query->where('stage_id', $stageId);
    }

    /**
     * Scope query to only include review rounds with given round number.
     */
    public function scopeWithRound($query, int $round)
    {
        return $query->where('round', $round);
    }

    /**
     * Scope query to only include review rounds with given status.
     */
    public function scopeWithStatus($query, int $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include review rounds with given submission file id.
     */
    public function scopeWithSubmissionFileId($query, int $submissionFileId)
    {
        return $query
            ->join(
                'review_round_files as rrf',
                'review_rounds.review_round_id',
                '=',
                'rrf.review_round_id'
            )
            ->where('rrf.submission_file_id', $submissionFileId);
    }

    /**
     * Scope a query to only include review rounds with given context id.
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->join('submissions', 'review_rounds.submission_id', '=', 'submissions.submission_id')
            ->where('submissions.context_id', $contextId);
    }
}
