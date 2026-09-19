<?php

/**
 * @file classes/testing/scenario/Processor/ReviewRoundProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewRoundProcessor
 *
 * @brief Assigns reviewers to one review round with a realistic final
 *        status, resolving recommendations and writing comment rows.
 *
 * Called inline by DecisionProcessor right after each round-creating
 * decision (sendExternalReview, newExternalRound). Does NOT implement
 * ScenarioProcessor directly because its input shape — one round's
 * reviewers plus the round ID — is narrower than the top-level spec
 * other processors accept.
 */

namespace PKP\testing\scenario\Processor;

use APP\core\Application;
use APP\facades\Repo;
use APP\notification\NotificationManager;
use Carbon\Carbon;
use PKP\context\Context;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\log\event\PKPSubmissionEventLogEntry;
use PKP\notification\Notification;
use PKP\security\Role;
use PKP\stageAssignment\StageAssignment;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\submission\reviewer\recommendation\ReviewerRecommendation;
use PKP\testing\scenario\ScenarioContext;

class ReviewRoundProcessor
{
    /** Review method strings accepted in the spec → ReviewAssignment constants. */
    private const METHOD_MAP = [
        'anonymous' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_ANONYMOUS,
        'doubleAnonymous' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_DOUBLEANONYMOUS,
        'open' => ReviewAssignment::SUBMISSION_REVIEW_METHOD_OPEN,
    ];

    /** Recommendation strings → localeKey of the default reviewer_recommendations row. */
    private const RECOMMENDATION_KEYS = [
        'accept' => 'reviewer.article.decision.accept',
        'pendingRevisions' => 'reviewer.article.decision.pendingRevisions',
        'resubmitHere' => 'reviewer.article.decision.resubmitHere',
        'resubmitElsewhere' => 'reviewer.article.decision.resubmitElsewhere',
        'decline' => 'reviewer.article.decision.decline',
        'seeComments' => 'reviewer.article.decision.seeComments',
    ];

    /**
     * Defaults from PKP\controllers\grid\users\reviewer\form\traits\HasReviewDueDate.
     * The UI's Add Reviewer form prefills these when the context's
     * `numWeeksPerReview` / `numWeeksPerResponse` aren't configured.
     */
    private const REVIEW_SUBMIT_DEFAULT_DUE_WEEKS = 4;
    private const REVIEW_RESPONSE_DEFAULT_DUE_WEEKS = 3;

    /**
     * @param array $roundSpec  Shape: ['reviewers' => [...]]
     * @return array  Fragment for the scenario response keyed to this round.
     */
    public function run(int $roundId, int $round, array $roundSpec, ScenarioContext $ctx): array
    {
        $submissionId = $ctx->submissionId();
        $contextId = $ctx->submissionContextId();
        $reviewerFragments = [];

        foreach ($roundSpec['reviewers'] ?? [] as $reviewerSpec) {
            $fragment = $this->assignReviewer($reviewerSpec, $roundId, $round, $submissionId, $contextId, $ctx);
            $reviewerFragments[] = $fragment;
        }

        $ctx->recordReviewRound($round, $roundId, $reviewerFragments);

        return $reviewerFragments;
    }

    private function assignReviewer(
        array $reviewerSpec,
        int $roundId,
        int $round,
        int $submissionId,
        int $contextId,
        ScenarioContext $ctx
    ): array {
        $reviewer = $ctx->userByUsername($reviewerSpec['user']);
        $methodString = $reviewerSpec['method'] ?? 'anonymous';
        $method = self::METHOD_MAP[$methodString]
            ?? throw new \InvalidArgumentException("Unknown review method '{$methodString}'. Use 'anonymous' | 'doubleAnonymous' | 'open'.");

        $now = Core::getCurrentDate();
        $createParams = [
            'submissionId' => $submissionId,
            'reviewerId' => $reviewer->getId(),
            'reviewRoundId' => $roundId,
            'stageId' => WORKFLOW_STAGE_ID_EXTERNAL_REVIEW,
            'reviewMethod' => $method,
            // Round number on the assignment must match the round it's
            // attached to, otherwise updateReviewRoundStatus (called by
            // Repo::reviewAssignment::add/edit) looks up the wrong round
            // and overwrites its status — most visible in multi-round
            // scenarios where round 1's REVISIONS_REQUESTED gets clobbered
            // when round-2 reviewers are added.
            'round' => $round,
            'dateAssigned' => $now,
            'dateNotified' => $now,
        ];

        // Always populate dateDue / dateResponseDue. The Add Reviewer
        // form (HasReviewDueDate trait) computes these from the context's
        // numWeeksPerReview / numWeeksPerResponse settings; without
        // them set on the row, UI surfaces that compute "days
        // overdue" render NULL → "overdue by 0 days".
        $context = $this->resolveContext($contextId);
        $createParams['dateDue'] = $reviewerSpec['reviewDueDate']
            ?? $this->defaultReviewDueDate($context);
        $createParams['dateResponseDue'] = $reviewerSpec['responseDueDate']
            ?? $this->defaultResponseDueDate($context);

        $assignment = Repo::reviewAssignment()->newDataObject($createParams);
        $assignmentId = Repo::reviewAssignment()->add($assignment);
        $assignment = Repo::reviewAssignment()->get($assignmentId);

        // Mirror EditorAction::addReviewer side effects:
        //   - REVIEW_ASSIGNMENT task notification for the reviewer
        //   - SUBMISSION_LOG_REVIEW_ASSIGN event-log row
        // Mail send + email_log entry are intentionally skipped (Mail::fake()
        // would suppress them anyway).
        $this->createReviewAssignmentNotification($reviewer->getId(), $contextId, $assignmentId);
        $this->writeReviewAssignEventLog($assignment, $reviewer, $submissionId);

        $status = $reviewerSpec['status'] ?? 'invited';
        $statusEdits = $this->statusFieldEdits($status, $now, $reviewerSpec, $contextId);
        if (!empty($statusEdits)) {
            Repo::reviewAssignment()->edit($assignment, $statusEdits);
        }

        // Mirror PKPReviewerReviewStep3Form::execute side effects on
        // completion (notify editors, remove the reviewer's task,
        // SUBMISSION_LOG_REVIEW_READY event-log row).
        // For terminal-but-not-completed states (declined / cancelled),
        // production removes the task notification too — UnassignReviewerForm
        // and the reviewer's decline button both clean it up.
        if (in_array($status, ['completed', 'declined', 'cancelled'], true)) {
            $this->removeReviewAssignmentNotification($assignmentId, $reviewer->getId());
        }
        if ($status === 'completed') {
            $this->notifyEditorsOnCompletion($submissionId, $assignmentId);
            $this->writeReviewReadyEventLog($assignment, $reviewer, $submissionId);
            // Editor confirms the review (PKPReviewerGridHandler::reviewRead).
            // Production splits this from the reviewer's submit, but a
            // 'completed' status in the spec means "fully done" — both
            // halves landed. Set dateConsidered (the editor-side stamp)
            // and write the SUBMISSION_LOG_REVIEW_CONFIRMED event log.
            // The `considered` flag was already set to CONSIDERED by
            // statusFieldEdits().
            Repo::reviewAssignment()->edit(
                Repo::reviewAssignment()->get($assignmentId),
                ['dateConsidered' => $now]
            );
            $this->writeReviewConfirmedEventLog($assignment, $reviewer, $submissionId);
        }

        // Comments are written only on completed reviews; the processor
        // writes one submission_comments row per non-empty comment field.
        if ($status === 'completed' && !empty($reviewerSpec['comments'])) {
            $this->writeReviewComments(
                $assignmentId,
                $submissionId,
                $reviewer->getId(),
                $reviewerSpec['comments']
            );
        }

        return [
            'username' => $reviewerSpec['user'],
            'reviewAssignmentId' => $assignmentId,
            'status' => $status,
            'recommendation' => $reviewerSpec['recommendation'] ?? null,
        ];
    }

    /**
     * Create the LEVEL_TASK NOTIFICATION_TYPE_REVIEW_ASSIGNMENT row that
     * EditorAction::addReviewer creates for the reviewer. Lets the
     * reviewer's dashboard surface the assignment as a task.
     */
    private function createReviewAssignmentNotification(int $reviewerId, int $contextId, int $assignmentId): void
    {
        $notificationManager = new NotificationManager();
        $notificationManager->createNotification(
            $reviewerId,
            Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT,
            $contextId,
            Application::ASSOC_TYPE_REVIEW_ASSIGNMENT,
            $assignmentId,
            Notification::NOTIFICATION_LEVEL_TASK
        );
    }

    /**
     * Remove the reviewer's REVIEW_ASSIGNMENT task notification — fired
     * when the reviewer completes the review (PKPReviewerReviewStep3Form),
     * declines (reviewer-side decline form), or is unassigned
     * (UnassignReviewerForm). Idempotent.
     */
    private function removeReviewAssignmentNotification(int $assignmentId, int $reviewerId): void
    {
        Notification::withAssoc(Application::ASSOC_TYPE_REVIEW_ASSIGNMENT, $assignmentId)
            ->withUserId($reviewerId)
            ->withType(Notification::NOTIFICATION_TYPE_REVIEW_ASSIGNMENT)
            ->delete();
    }

    /**
     * Mirror PKPReviewerReviewStep3Form::execute editor-notification loop:
     * for each Manager / Sub-editor on the submission stage, create a
     * NOTIFICATION_TYPE_REVIEWER_COMMENT row. Email sends are intentionally
     * skipped (Mail::fake() would no-op and email_log rows aren't
     * inspected by the test suite).
     */
    private function notifyEditorsOnCompletion(int $submissionId, int $assignmentId): void
    {
        $submission = Repo::submission()->get($submissionId);
        $contextId = (int)$submission->getData('contextId');
        $stageId = (int)$submission->getData('stageId');

        $stageAssignments = StageAssignment::withSubmissionIds([$submissionId])
            ->withStageIds([$stageId])
            ->whereHas('userGroup', function ($query) {
                $query->withRoleIds([Role::ROLE_ID_MANAGER, Role::ROLE_ID_SUB_EDITOR]);
            })
            ->get();

        $notificationManager = new NotificationManager();
        $seen = [];
        foreach ($stageAssignments as $stageAssignment) {
            $userId = (int)$stageAssignment->userId;
            if (isset($seen[$userId])) {
                continue;
            }
            $seen[$userId] = true;
            $notificationManager->createNotification(
                $userId,
                Notification::NOTIFICATION_TYPE_REVIEWER_COMMENT,
                $contextId,
                Application::ASSOC_TYPE_REVIEW_ASSIGNMENT,
                $assignmentId
            );
        }
    }

    /**
     * Append a SUBMISSION_LOG_REVIEW_ASSIGN event-log row mirroring
     * EditorAction::addReviewer (lines 121–135). Attributes to the admin
     * user since the Processor runs out-of-session.
     */
    private function writeReviewAssignEventLog(ReviewAssignment $assignment, $reviewer, int $submissionId): void
    {
        $admin = Repo::user()->getByUsername('admin', true);
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_ASSIGN,
            'userId' => $admin?->getId(),
            'message' => 'log.review.reviewerAssigned',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'reviewAssignment' => $assignment->getId(),
            'reviewerName' => $reviewer->getFullName(),
            'submissionId' => $submissionId,
            'stageId' => $assignment->getStageId(),
            'round' => $assignment->getRound(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    /**
     * Append a SUBMISSION_LOG_REVIEW_READY event-log row mirroring
     * PKPReviewerReviewStep3Form::execute (lines 257–272).
     */
    private function writeReviewReadyEventLog(ReviewAssignment $assignment, $reviewer, int $submissionId): void
    {
        $admin = Repo::user()->getByUsername('admin', true);
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_READY,
            'userId' => $admin?->getId(),
            'message' => 'log.review.reviewReady',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'reviewAssignmentId' => $assignment->getId(),
            'reviewerName' => $reviewer->getFullName(),
            'submissionId' => $submissionId,
            'round' => $assignment->getRound(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    /**
     * Append a SUBMISSION_LOG_REVIEW_CONFIRMED event-log row mirroring
     * the editor's "confirm review" flow in
     * PKPReviewerGridHandler::reviewRead (lines 791–807). Production
     * gates this on `$reviewAssignment->isRead()`; for a
     * scenario-seeded "completed" status the editor is presumed to
     * have read + confirmed, so we always write it.
     */
    private function writeReviewConfirmedEventLog(ReviewAssignment $assignment, $reviewer, int $submissionId): void
    {
        $admin = Repo::user()->getByUsername('admin', true);
        $eventLog = Repo::eventLog()->newDataObject([
            'assocType' => Application::ASSOC_TYPE_SUBMISSION,
            'assocId' => $submissionId,
            'eventType' => PKPSubmissionEventLogEntry::SUBMISSION_LOG_REVIEW_CONFIRMED,
            'userId' => $admin?->getId(),
            'message' => 'log.review.reviewConfirmed',
            'isTranslated' => false,
            'dateLogged' => Core::getCurrentDate(),
            'editorName' => $admin?->getFullName(),
            'submissionId' => $submissionId,
            'round' => $assignment->getRound(),
        ]);
        Repo::eventLog()->add($eventLog);
    }

    /**
     * Default `dateDue` (review submit deadline). Mirrors the UI's
     * HasReviewDueDate trait — `today + numWeeksPerReview` (or 4 weeks
     * if the context's setting is unconfigured).
     */
    private function defaultReviewDueDate(?Context $context): string
    {
        $numWeeks = (int)($context?->getData('numWeeksPerReview') ?? 0);
        if ($numWeeks <= 0) {
            $numWeeks = self::REVIEW_SUBMIT_DEFAULT_DUE_WEEKS;
        }
        return Carbon::today()->endOfDay()->addWeeks($numWeeks)->toDateTimeString();
    }

    /**
     * Default `dateResponseDue` (reviewer's accept/decline deadline).
     * Mirrors the UI's HasReviewDueDate trait — `today +
     * numWeeksPerResponse` (or 3 weeks if the context's setting is
     * unconfigured).
     */
    private function defaultResponseDueDate(?Context $context): string
    {
        $numWeeks = (int)($context?->getData('numWeeksPerResponse') ?? 0);
        if ($numWeeks <= 0) {
            $numWeeks = self::REVIEW_RESPONSE_DEFAULT_DUE_WEEKS;
        }
        return Carbon::today()->endOfDay()->addWeeks($numWeeks)->toDateTimeString();
    }

    /**
     * Look up the context for the contextId carried on the
     * ScenarioContext. Used for default-due-date computation.
     */
    private function resolveContext(int $contextId): ?Context
    {
        return app()->get('context')->get($contextId);
    }

    /**
     * Convert a final-status string into the exact combination of
     * date/flag fields the ReviewAssignment needs. Mirrors the table
     * in the Phase 2 plan; sourced from ReviewAssignment::getStatus()
     * at ReviewAssignment.php:674-731.
     */
    private function statusFieldEdits(string $status, string $now, array $reviewerSpec, int $contextId): array
    {
        switch ($status) {
            case 'invited':
                return [];

            case 'accepted':
                return ['dateConfirmed' => $now];

            case 'declined':
                return ['dateConfirmed' => $now, 'declined' => true];

            case 'cancelled':
                return [
                    'dateConfirmed' => $now,
                    'cancelled' => true,
                    'dateCancelled' => $now,
                ];

            case 'completed':
                $edits = [
                    'dateConfirmed' => $now,
                    'dateCompleted' => $now,
                    'considered' => ReviewAssignment::REVIEW_ASSIGNMENT_CONSIDERED,
                ];
                if (isset($reviewerSpec['recommendation'])) {
                    $edits['reviewerRecommendationId'] = $this->resolveRecommendationId(
                        $reviewerSpec['recommendation'],
                        $contextId
                    );
                }
                return $edits;

            default:
                throw new \InvalidArgumentException(
                    "Unknown reviewer status '{$status}'. Use 'invited' | 'accepted' | 'declined' | 'completed' | 'cancelled'."
                );
        }
    }

    private function resolveRecommendationId(string $recommendation, int $contextId): int
    {
        $localeKey = self::RECOMMENDATION_KEYS[$recommendation]
            ?? throw new \InvalidArgumentException(
                "Unknown recommendation '{$recommendation}'. Use one of: "
                . implode(', ', array_keys(self::RECOMMENDATION_KEYS))
            );

        $row = ReviewerRecommendation::withContextId($contextId)
            ->where(ReviewerRecommendation::DEFAULT_RECOMMENDATION_TRANSLATION_KEY, $localeKey)
            ->first();

        if (!$row) {
            throw new \RuntimeException(
                "reviewer_recommendations row for '{$localeKey}' not found in context {$contextId}. "
                . "These are seeded at context creation — was the journal created via PKPContextService?"
            );
        }

        return (int)$row->id;
    }

    /**
     * Write zero, one, or two submission_comments rows per completed reviewer:
     *   comments.toEditor → viewable=0 (private to editors)
     *   comments.toAuthor → viewable=1 (visible to authors too)
     */
    private function writeReviewComments(int $assignmentId, int $submissionId, int $reviewerId, array $commentsSpec): void
    {
        /** @var \PKP\submission\SubmissionCommentDAO $dao */
        $dao = DAORegistry::getDAO('SubmissionCommentDAO');

        foreach ([
            'toEditor' => 0,  // private
            'toAuthor' => 1,  // viewable to authors
        ] as $field => $viewable) {
            $text = $commentsSpec[$field] ?? null;
            if (!is_string($text) || $text === '') {
                continue;
            }
            $comment = $dao->newDataObject();
            $comment->setCommentType(SubmissionComment::COMMENT_TYPE_PEER_REVIEW);
            $comment->setRoleId(Role::ROLE_ID_REVIEWER);
            $comment->setSubmissionId($submissionId);
            $comment->setAssocId($assignmentId);
            $comment->setAuthorId($reviewerId);
            $comment->setCommentTitle('');
            $comment->setComments($text);
            $comment->setViewable($viewable);
            $comment->setDatePosted(Core::getCurrentDate());
            $comment->setDateModified(Core::getCurrentDate());
            $dao->insertObject($comment);
        }
    }
}
