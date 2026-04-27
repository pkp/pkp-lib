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

use APP\facades\Repo;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\security\Role;
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

        if (isset($reviewerSpec['responseDueDate'])) {
            $createParams['dateResponseDue'] = $reviewerSpec['responseDueDate'];
        }
        if (isset($reviewerSpec['reviewDueDate'])) {
            $createParams['dateDue'] = $reviewerSpec['reviewDueDate'];
        }

        $assignment = Repo::reviewAssignment()->newDataObject($createParams);
        $assignmentId = Repo::reviewAssignment()->add($assignment);
        $assignment = Repo::reviewAssignment()->get($assignmentId);

        $status = $reviewerSpec['status'] ?? 'invited';
        $statusEdits = $this->statusFieldEdits($status, $now, $reviewerSpec, $contextId);
        if (!empty($statusEdits)) {
            Repo::reviewAssignment()->edit($assignment, $statusEdits);
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
