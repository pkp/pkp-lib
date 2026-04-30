<?php

/**
 * @file classes/testing/scenario/Processor/UserAssignmentProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserAssignmentProcessor
 *
 * @brief Resolves users (creating new ones if a `password` field is supplied,
 *        otherwise looking up the existing baseline by username) and assigns
 *        them to the freshly-created context's role groups.
 *
 * Replaces the bootstrap-only UserProcessor (which always created) and the
 * scenario-only ContextUserProcessor (which always associated). The two
 * branches are now signalled by spec presence: bootstrap users carry a
 * `password` field (computed by the JS client via getPassword()), per-test
 * scratch-journal users do not.
 */

namespace PKP\testing\scenario\Processor;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\security\Validation;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\testing\scenario\UserGroupLookup;

class UserAssignmentProcessor implements ScenarioProcessor
{
    public function appliesTo(array $spec): bool
    {
        return !empty($spec['users']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $contextId = $ctx->journalId($spec['path']);

        foreach ($spec['users'] as $userSpec) {
            $user = $this->resolveOrCreate($userSpec);
            $userId = $user->getId();

            foreach ($userSpec['roles'] ?? [] as $roleString) {
                $userGroupId = (int)UserGroupLookup::userGroupForRole($contextId, $roleString)->id;
                Repo::userGroup()->assignUserToGroup($userId, $userGroupId);
            }

            $ctx->recordUser($userSpec['username'], ['id' => $userId]);
        }

        return [];
    }

    /**
     * Look up by username; if missing AND the spec carries a `password`,
     * create the user. If missing and no password supplied, fail loudly —
     * a typo should never silently mint a new user on a per-test scratch
     * journal.
     */
    private function resolveOrCreate(array $userSpec): \PKP\user\User
    {
        $username = $userSpec['username'];
        $existing = Repo::user()->getByUsername($username, true);
        if ($existing) {
            return $existing;
        }

        if (empty($userSpec['password'])) {
            throw new \RuntimeException(
                "User '{$username}' not found and no `password` supplied to create it. " .
                'Per-test scratch-journal specs must reference users that already exist on the database.'
            );
        }

        $locale = $userSpec['locale'] ?? 'en';
        $user = Repo::user()->newDataObject([
            // Property is `userName` (camelCase, capital N) — see User DAO's
            // primaryTableColumns map. Passing 'username' silently drops it
            // and the INSERT fails the NOT NULL constraint.
            'userName' => $username,
            'password' => Validation::encryptCredentials($username, $userSpec['password']),
            'email' => $userSpec['email'] ?? ($username . '@mailinator.com'),
            'givenName' => [
                $locale => $userSpec['givenName'] ?? $username,
            ],
            'familyName' => [
                $locale => $userSpec['familyName'] ?? '',
            ],
            'country' => $userSpec['country'] ?? 'US',
            'affiliation' => [
                $locale => $userSpec['affiliation'] ?? '',
            ],
            'mustChangePassword' => !empty($userSpec['mustChangePassword']),
            // date_registered is NOT NULL without a DB default; the normal
            // UI registration form sets it via PKPInstall but Repo::user()->add
            // does no defaulting.
            'dateRegistered' => Core::getCurrentDate(),
            // Both production user-creation paths (RegistrationForm and
            // UserRoleAssignmentReceiveController) default new users to
            // having inline help visible. Match.
            'inlineHelp' => 1,
        ]);

        Repo::user()->add($user);
        return $user;
    }
}
