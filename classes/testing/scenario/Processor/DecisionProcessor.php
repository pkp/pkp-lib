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

            $decision = Repo::decision()->newDataObject($decisionParams);
            $decisionId = Repo::decision()->add($decision);
            $ctx->recordDecision($decisionSpec['type'], $decisionId);

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
}
