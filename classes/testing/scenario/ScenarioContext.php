<?php

/**
 * @file classes/testing/scenario/ScenarioContext.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ScenarioContext
 *
 * @brief Shared state passed between scenario processors during a single
 *        scenario build.
 *
 * Holds lookup helpers (resolve users by username, journals by path) and
 * a running ID map that later processors can read from. The controller
 * creates one instance per request and threads it through every processor
 * in order.
 */

namespace PKP\testing\scenario;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\user\User;

class ScenarioContext
{
    /** Running map of created entities, keyed by natural keys from the spec. */
    private array $idMap = [
        'journals' => [],
        'users' => [],
    ];

    /** Phase 2: the single submission being built by a scenario request. */
    private ?array $scenarioSubmission = null;

    /**
     * Soft-fail warnings recorded by processors during the build. Emitted
     * to the response body so test code can assert without the request
     * itself failing — e.g. when a decision spec requests a notifyReviewers
     * action against a decision type that has no such step.
     */
    private array $warnings = [];

    /** Cache of resolved User objects keyed by username. */
    private array $userCache = [];

    /** Cache of resolved Context objects keyed by urlPath. */
    private array $contextCache = [];

    public function recordJournal(string $path, array $fragment): void
    {
        $this->idMap['journals'][$path] = $fragment;
    }

    public function recordUser(string $username, array $fragment): void
    {
        $this->idMap['users'][$username] = $fragment;
    }

    public function journalId(string $path): int
    {
        return $this->idMap['journals'][$path]['id']
            ?? throw new \RuntimeException("Scenario context has no journal recorded for path '{$path}'");
    }

    /**
     * Return the ID of the first journal recorded during this scenario,
     * or null if none has been created yet. Used by the shared
     * PKPContextScenarioController's post-create hook to hand a
     * freshly-built context ID to the OJS-specific subclass.
     */
    public function firstJournalId(): ?int
    {
        foreach ($this->idMap['journals'] as $fragment) {
            return (int)($fragment['id'] ?? 0);
        }
        return null;
    }

    public function userByUsername(string $username): User
    {
        if (!isset($this->userCache[$username])) {
            $user = Repo::user()->getByUsername($username, true);
            if (!$user) {
                throw new \RuntimeException("Scenario context cannot resolve user '{$username}' — is it in the spec's users section or admin from install?");
            }
            $this->userCache[$username] = $user;
        }
        return $this->userCache[$username];
    }

    public function contextByPath(string $path): Context
    {
        if (!isset($this->contextCache[$path])) {
            // OJS doesn't have Repo::context(); contexts (Journal/Press/Server)
            // are DAO-fetched via the app's ContextDAO.
            $context = Application::getContextDAO()->getByPath($path);
            if (!$context) {
                throw new \RuntimeException("Scenario context cannot resolve journal '{$path}' — was it created by the bootstrap?");
            }
            $this->contextCache[$path] = $context;
        }
        return $this->contextCache[$path];
    }

    public function idMap(): array
    {
        return $this->idMap;
    }

    /**
     * Phase 2: record the submission a submission-scenario is building.
     * Later processors read these IDs instead of re-querying the DB.
     */
    public function recordSubmission(int $submissionId, int $firstPublicationId, int $contextId): void
    {
        $this->scenarioSubmission = [
            'id' => $submissionId,
            'firstPublicationId' => $firstPublicationId,
            'contextId' => $contextId,
            'participants' => [],
            'decisions' => [],
            'reviewRounds' => [],
            'publications' => [],
        ];
    }

    public function submissionId(): int
    {
        return $this->scenarioSubmission['id']
            ?? throw new \RuntimeException('ScenarioContext: no submission recorded yet');
    }

    public function firstPublicationId(): int
    {
        return $this->scenarioSubmission['firstPublicationId']
            ?? throw new \RuntimeException('ScenarioContext: no submission recorded yet');
    }

    public function submissionContextId(): int
    {
        return $this->scenarioSubmission['contextId']
            ?? throw new \RuntimeException('ScenarioContext: no submission recorded yet');
    }

    public function recordParticipant(string $username, string $role, int $stageAssignmentId): void
    {
        $this->scenarioSubmission['participants'][] = [
            'username' => $username,
            'role' => $role,
            'stageAssignmentId' => $stageAssignmentId,
        ];
    }

    public function recordDecision(string $type, int $decisionId): void
    {
        $this->scenarioSubmission['decisions'][] = [
            'type' => $type,
            'id' => $decisionId,
        ];
    }

    /**
     * Annotate the most recently recorded decision with its toEditor
     * comment id + body, so test code can assert the row's existence
     * + content without a dedicated read endpoint. Mirrors the way
     * recordReviewRound returns reviewer ids.
     */
    public function recordEditorDecisionComment(int $decisionId, int $commentId, string $body): void
    {
        if (empty($this->scenarioSubmission['decisions'])) {
            return;
        }
        $last = count($this->scenarioSubmission['decisions']) - 1;
        if (($this->scenarioSubmission['decisions'][$last]['id'] ?? null) !== $decisionId) {
            // Out-of-order recording shouldn't happen because the processor
            // adds the comment immediately after the decision row, but be
            // defensive — fall back to a top-level append.
            return;
        }
        $this->scenarioSubmission['decisions'][$last]['toEditorComment'] = [
            'id' => $commentId,
            'body' => $body,
        ];
    }

    public function recordReviewRound(int $round, int $roundId, array $reviewers): void
    {
        $this->scenarioSubmission['reviewRounds'][] = [
            'round' => $round,
            'roundId' => $roundId,
            'reviewers' => $reviewers,
        ];
    }

    public function recordPublication(array $fragment): void
    {
        $this->scenarioSubmission['publications'][] = $fragment;
    }

    public function addWarning(string $message): void
    {
        $this->warnings[] = $message;
    }

    public function warnings(): array
    {
        return $this->warnings;
    }

    public function submissionResponse(string $tag): array
    {
        if ($this->scenarioSubmission === null) {
            throw new \RuntimeException('ScenarioContext: no submission recorded');
        }
        return [
            'submission' => ['id' => $this->scenarioSubmission['id']],
            'publications' => $this->scenarioSubmission['publications'],
            'participants' => $this->scenarioSubmission['participants'],
            'decisions' => $this->scenarioSubmission['decisions'],
            'reviewRounds' => $this->scenarioSubmission['reviewRounds'],
            'warnings' => $this->warnings,
            'tag' => $tag,
        ];
    }

    /**
     * Phase E0: response for a context-scenario request that builds a
     * scratch journal (or press/server). Primary manager is derived
     * from the first user in the spec whose roles include 'manager'.
     */
    public function contextScenarioResponse(array $spec): array
    {
        $path = $spec['path'];
        $journal = $this->idMap['journals'][$path]
            ?? throw new \RuntimeException("ScenarioContext: no context recorded at path '{$path}'");

        $primaryManager = null;
        foreach ($spec['users'] ?? [] as $user) {
            if (in_array('manager', $user['roles'] ?? [], true)) {
                $primaryManager = ['username' => $user['username']];
                break;
            }
        }

        return [
            'context' => [
                'id' => $journal['id'],
                'path' => $path,
                'name' => $journal['name'] ?? null,
                'primaryLocale' => $journal['primaryLocale'] ?? null,
                'primaryManager' => $primaryManager,
            ],
            'tag' => $spec['tag'] ?? '',
        ];
    }
}
