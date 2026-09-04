<?php

/**
 * @file classes/testing/scenario/Processor/DecisionProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DecisionProcessor
 *
 * @brief Records a sequence of editorial decisions on the scenario's
 *        submission, interleaving ReviewRoundProcessor calls right after
 *        each round-creating decision.
 *
 * Reads submission state fresh per iteration so decisions see the
 * cascades of their predecessors (stage advance, status change, round
 * auto-creation).
 */

namespace PKP\testing\scenario\Processor;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\decision\Decision;
use PKP\decision\DecisionType;
use PKP\decision\types\traits\NotifyReviewers;
use PKP\security\Role;
use PKP\submission\reviewAssignment\ReviewAssignment;
use PKP\submission\SubmissionComment;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;

class DecisionProcessor implements ScenarioProcessor
{
    /**
     * Friendly decision-type strings → Decision::* constants. Mirrors the
     * vocabulary the existing Cypress helpers use so test code transfers
     * directly. Explicitly does not include RECOMMEND_* (deferred to a
     * later phase — editor-to-editor recommendations are a distinct flow).
     */
    private const DECISION_MAP = [
        'initialDecline' => Decision::INITIAL_DECLINE,
        'sendExternalReview' => Decision::EXTERNAL_REVIEW,
        'skipExternalReview' => Decision::SKIP_EXTERNAL_REVIEW,
        'accept' => Decision::ACCEPT,
        'decline' => Decision::DECLINE,
        'requestRevisions' => Decision::PENDING_REVISIONS,
        'resubmit' => Decision::RESUBMIT,
        'newExternalRound' => Decision::NEW_EXTERNAL_ROUND,
        'cancelReviewRound' => Decision::CANCEL_REVIEW_ROUND,
        'sendToProduction' => Decision::SEND_TO_PRODUCTION,
        'backFromCopyediting' => Decision::BACK_FROM_COPYEDITING,
        'backFromProduction' => Decision::BACK_FROM_PRODUCTION,
        'revertDecline' => Decision::REVERT_DECLINE,
        'revertInitialDecline' => Decision::REVERT_INITIAL_DECLINE,
    ];

    /** Decision constants whose runAdditionalActions() creates a new review_rounds row. */
    private const ROUND_CREATING = [
        Decision::EXTERNAL_REVIEW,
        Decision::NEW_EXTERNAL_ROUND,
    ];

    public function __construct(private ReviewRoundProcessor $reviewRoundProcessor)
    {
    }

    public function appliesTo(array $spec): bool
    {
        return !empty($spec['decisions']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $submissionId = $ctx->submissionId();
        $reviewRoundsIdx = 0;
        $reviewRoundSpecs = $spec['reviewRounds'] ?? [];

        foreach ($spec['decisions'] as $decisionSpec) {
            $decisionConst = $this->mapDecisionType($decisionSpec['type']);
            $editor = $ctx->userByUsername($decisionSpec['by']);

            // Re-read submission each iteration so the stageId we pass
            // reflects cascades from prior decisions in this same run.
            $submission = Repo::submission()->get($submissionId);
            $decisionType = Repo::decision()->getDecisionType($decisionConst);
            if (!$decisionType) {
                throw new \RuntimeException("No DecisionType registered for constant {$decisionConst}");
            }

            $decisionParams = [
                'submissionId' => $submissionId,
                'decision' => $decisionConst,
                'stageId' => $decisionType->getStageId(),
                'editorId' => $editor->getId(),
                'dateDecided' => Core::getCurrentDate(),
            ];

            // In-review decisions need a reviewRoundId; look up the active round.
            if ($decisionType->isInReview()) {
                $decisionParams['reviewRoundId'] = $this->currentReviewRoundId($submissionId, $decisionType->getStageId());
            }

            // Build optional notify-author / notify-reviewer email actions
            // mirroring what the live decision form posts. The actions array
            // is consumed by Repo::decision()->add() (it strips it off the
            // decision object and forwards to runAdditionalActions which
            // dispatches per-trait sendAuthorEmail / sendReviewersEmail).
            //
            // Mail is faked at the controller level (Mail::fake()) so this
            // does not actually send mail — it just exercises the same code
            // path the form runs and writes the same email-log rows.
            $actions = $this->buildActions($decisionSpec, $decisionType, $submission, $ctx);

            $decision = Repo::decision()->newDataObject($decisionParams);
            if (!empty($actions)) {
                $decision->setData('actions', $actions);
            }
            $decisionId = Repo::decision()->add($decision);
            $ctx->recordDecision($decisionSpec['type'], $decisionId);

            // Internal editor-only note. The legacy decision form once wrote
            // submission_comments rows with COMMENT_TYPE_EDITOR_DECISION; the
            // current Vue decision UI doesn't expose this field but the
            // constant + storage are still wired through. We mirror the
            // shape ReviewRoundProcessor uses for COMMENT_TYPE_PEER_REVIEW
            // (set role / submission / assoc / author / comments / viewable
            // / datePosted / dateModified) so the row reads back identically.
            if (!empty($decisionSpec['toEditor'])) {
                $commentId = $this->writeEditorDecisionComment(
                    $submission->getId(),
                    $decisionId,
                    $editor->getId(),
                    $decisionSpec['toEditor']
                );
                $ctx->recordEditorDecisionComment($decisionId, $commentId, $decisionSpec['toEditor']);
            }

            // If the decision just created a round, delegate to ReviewRoundProcessor
            // with the next spec.reviewRounds[] entry.
            if (in_array($decisionConst, self::ROUND_CREATING, true) && isset($reviewRoundSpecs[$reviewRoundsIdx])) {
                /** @var \PKP\submission\reviewRound\ReviewRoundDAO $reviewRoundDao */
                $reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
                $round = $reviewRoundDao->getLastReviewRoundBySubmissionId($submissionId, WORKFLOW_STAGE_ID_EXTERNAL_REVIEW);
                if (!$round) {
                    throw new \RuntimeException(
                        "Decision '{$decisionSpec['type']}' was expected to create a review round "
                        . "but no round exists after Repo::decision()->add() — is the decision cascading correctly?"
                    );
                }
                $this->reviewRoundProcessor->run(
                    (int)$round->getId(),
                    (int)$round->getRound(),
                    $reviewRoundSpecs[$reviewRoundsIdx],
                    $ctx
                );
                $reviewRoundsIdx++;
            }
        }

        return [];
    }

    private function mapDecisionType(string $friendly): int
    {
        if (!isset(self::DECISION_MAP[$friendly])) {
            throw new \InvalidArgumentException(
                "Unknown decision type '{$friendly}'. Known: "
                . implode(', ', array_keys(self::DECISION_MAP))
            );
        }
        return self::DECISION_MAP[$friendly];
    }

    private function currentReviewRoundId(int $submissionId, int $stageId): int
    {
        /** @var \PKP\submission\reviewRound\ReviewRoundDAO $dao */
        $dao = DAORegistry::getDAO('ReviewRoundDAO');
        $round = $dao->getLastReviewRoundBySubmissionId($submissionId, $stageId);
        if (!$round) {
            throw new \RuntimeException(
                "In-review decision requires a review round to exist for submission {$submissionId} "
                . "at stage {$stageId}. Did a prior decision (sendExternalReview) create one?"
            );
        }
        return (int)$round->getId();
    }

    /**
     * Build the email-action array consumed by Repo::decision()->add().
     *
     * Field names + shape match what the live decision form posts: each
     * action has an `id` ('notifyAuthors' / 'notifyReviewers'), `subject`,
     * `body`, and (for notifyReviewers) a `recipients` int[] of reviewer
     * user IDs. See PKP\decision\DecisionType::validateEmailAction.
     *
     * Soft-fail on toReviewers when the decision type doesn't use the
     * NotifyReviewers trait (e.g. SkipExternalReview, InitialDecline) —
     * skipping silently and logging a warning rather than blowing up
     * keeps mixed-decision-list scenarios ergonomic.
     */
    private function buildActions(array $decisionSpec, DecisionType $decisionType, $submission, ScenarioContext $ctx): array
    {
        $actions = [];

        if (!empty($decisionSpec['toAuthor'])) {
            // recipients are intentionally omitted: the NotifyAuthors trait's
            // sendAuthorEmail() ignores the action's `recipients` and pulls
            // assigned authors from stage assignments instead. Setting an
            // empty array keeps validateEmailAction happy if anyone ever
            // re-runs validation downstream.
            $actions[] = [
                'id' => DecisionType::ACTION_NOTIFY_AUTHORS,
                'recipients' => [],
                'subject' => $this->defaultSubject($decisionSpec['type'], 'author'),
                'body' => $decisionSpec['toAuthor'],
            ];
        }

        if (!empty($decisionSpec['toReviewers'])) {
            if (!is_string($decisionSpec['toReviewers'])) {
                throw new \InvalidArgumentException(
                    "decision.toReviewers object form is forward-compat only; pass a string."
                );
            }

            if (!$this->decisionTypeNotifiesReviewers($decisionType)) {
                $ctx->addWarning(sprintf(
                    "Decision '%s' has no notifyReviewers action — toReviewers ignored.",
                    $decisionSpec['type']
                ));
            } else {
                $reviewerIds = $this->reviewerIdsForDecision($submission->getId(), $decisionType);
                if (empty($reviewerIds)) {
                    $ctx->addWarning(sprintf(
                        "Decision '%s' specifies toReviewers but no completed reviewer assignments exist on the active round — skipping notifyReviewers.",
                        $decisionSpec['type']
                    ));
                } else {
                    $actions[] = [
                        'id' => DecisionType::ACTION_NOTIFY_REVIEWERS,
                        'recipients' => $reviewerIds,
                        'subject' => $this->defaultSubject($decisionSpec['type'], 'reviewer'),
                        'body' => $decisionSpec['toReviewers'],
                    ];
                }
            }
        }

        return $actions;
    }

    /**
     * Whether this decision type uses the NotifyReviewers trait. The form
     * itself drives this off Steps registration; trait usage is the closest
     * stable signal we can read without instantiating Steps (which needs a
     * full request context).
     */
    private function decisionTypeNotifiesReviewers(DecisionType $decisionType): bool
    {
        $traits = [];
        $class = new \ReflectionClass($decisionType);
        while ($class) {
            $traits = array_merge($traits, $class->getTraitNames());
            $class = $class->getParentClass();
        }
        return in_array(NotifyReviewers::class, $traits, true);
    }

    /**
     * Reviewer user IDs eligible for notifyReviewers on the decision's
     * active round. Mirrors NotifyReviewers::validateNotifyReviewersAction
     * which queries getReviewerIds with REVIEW_ASSIGNMENT_COMPLETED.
     */
    private function reviewerIdsForDecision(int $submissionId, DecisionType $decisionType): array
    {
        $stageId = $decisionType->getStageId();
        /** @var \PKP\submission\reviewRound\ReviewRoundDAO $dao */
        $dao = DAORegistry::getDAO('ReviewRoundDAO');
        $round = $dao->getLastReviewRoundBySubmissionId($submissionId, $stageId);
        if (!$round) {
            return [];
        }
        return Repo::reviewAssignment()->getCollector()
            ->filterByReviewRoundIds([$round->getId()])
            ->filterBySubmissionIds([$submissionId])
            ->filterByStageId($stageId)
            ->getMany()
            ->filter(fn (ReviewAssignment $a) => in_array($a->getStatus(), ReviewAssignment::REVIEW_COMPLETE_STATUSES))
            ->map(fn (ReviewAssignment $a) => (int)$a->getReviewerId())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Default subject for a seeded notification email. The live form
     * pre-fills from the decision-type's mailable email template; we'd
     * have to instantiate the full mailable + template lookup chain to
     * mirror that. A literal subject keeps validateEmailAction happy
     * (subject is required) and is sufficient for the assertion the
     * sanity spec wants to make. Tests that need exact subjects can
     * override via toAuthor / toReviewers fields once they're objects.
     */
    private function defaultSubject(string $decisionType, string $audience): string
    {
        return sprintf('[scenario] %s — notify %s', $decisionType, $audience);
    }

    /**
     * Insert a submission_comments row of type COMMENT_TYPE_EDITOR_DECISION.
     *
     * Field-shape mirrors ReviewRoundProcessor::writeReviewComments (which
     * writes the COMMENT_TYPE_PEER_REVIEW sibling rows): submissionId,
     * assocId (the new decision row's id), authorId (the editor making
     * the decision), commentTitle (empty), comments (the toEditor body),
     * viewable=0 (private to editors), datePosted + dateModified.
     *
     * roleId is set to ROLE_ID_SUB_EDITOR — editor-decision comments are
     * always authored by an editor. The legacy Cypress flow set this to
     * the same value the form did when the field was still wired through
     * the live UI; the constant survives in SubmissionCommentDAO::_fromRow
     * so the row reads back correctly.
     */
    private function writeEditorDecisionComment(int $submissionId, int $decisionId, int $editorId, string $body): int
    {
        /** @var \PKP\submission\SubmissionCommentDAO $dao */
        $dao = DAORegistry::getDAO('SubmissionCommentDAO');
        $comment = $dao->newDataObject();
        $comment->setCommentType(SubmissionComment::COMMENT_TYPE_EDITOR_DECISION);
        $comment->setRoleId(Role::ROLE_ID_SUB_EDITOR);
        $comment->setSubmissionId($submissionId);
        $comment->setAssocId($decisionId);
        $comment->setAuthorId($editorId);
        $comment->setCommentTitle('');
        $comment->setComments($body);
        $comment->setViewable(0);
        $comment->setDatePosted(Core::getCurrentDate());
        $comment->setDateModified(Core::getCurrentDate());
        return (int) $dao->insertObject($comment);
    }
}
