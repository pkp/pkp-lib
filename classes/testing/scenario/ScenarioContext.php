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
            $context = Repo::context()->getByPath($path);
            if (!$context) {
                throw new \RuntimeException("Scenario context cannot resolve journal '{$path}' — is it in the spec's journals section?");
            }
            $this->contextCache[$path] = $context;
        }
        return $this->contextCache[$path];
    }

    public function idMap(): array
    {
        return $this->idMap;
    }
}
