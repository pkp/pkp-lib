<?php

/**
 * @file classes/testing/bootstrap/Processor/UserProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserProcessor
 *
 * @brief Creates user accounts and links them to the default user groups of
 *        the journals they belong to.
 *
 * Runs after JournalProcessor so the target journals (and the default user
 * groups auto-installed with them) exist. Maps friendly role strings from
 * the spec (e.g. 'editor', 'copyeditor') to OJS's canonical
 * default.groups.name.* locale keys defined in registry/userGroups.xml.
 */

namespace PKP\testing\bootstrap\Processor;

use APP\facades\Repo;
use PKP\core\Core;
use PKP\security\Validation;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\testing\scenario\UserGroupLookup;

class UserProcessor implements ScenarioProcessor
{
    public function appliesTo(array $spec): bool
    {
        return !empty($spec['users']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        foreach ($spec['users'] as $userSpec) {
            $this->createUser($userSpec, $ctx);
        }
        return [];
    }

    private function createUser(array $userSpec, ScenarioContext $ctx): void
    {
        $username = $userSpec['username'];
        $password = $this->derivePassword($username);

        // Property is `userName` (camelCase, capital N) — see User DAO's
        // primaryTableColumns map. Passing 'username' silently drops it
        // and the INSERT fails the NOT NULL constraint.
        $user = Repo::user()->newDataObject([
            'userName' => $username,
            'password' => Validation::encryptCredentials($username, $password),
            'email' => $userSpec['email'] ?? ($username . '@mailinator.com'),
            'givenName' => [
                ($userSpec['locale'] ?? 'en') => $userSpec['givenName'] ?? $username,
            ],
            'familyName' => [
                ($userSpec['locale'] ?? 'en') => $userSpec['familyName'] ?? '',
            ],
            'country' => $userSpec['country'] ?? 'US',
            'affiliation' => [
                ($userSpec['locale'] ?? 'en') => $userSpec['affiliation'] ?? '',
            ],
            'mustChangePassword' => !empty($userSpec['mustChangePassword']),
            // date_registered is NOT NULL without a DB default; the normal
            // UI registration form sets it via PKPInstall but Repo::user()->add
            // does no defaulting.
            'dateRegistered' => Core::getCurrentDate(),
        ]);

        $userId = Repo::user()->add($user);

        if (!empty($userSpec['journal']) && !empty($userSpec['roles'])) {
            $contextId = $ctx->journalId($userSpec['journal']);
            foreach ($userSpec['roles'] as $roleString) {
                $userGroupId = $this->resolveUserGroupId($contextId, $roleString);
                Repo::userGroup()->assignUserToGroup($userId, $userGroupId);
            }
        }

        $ctx->recordUser($username, ['id' => $userId]);
    }

    private function resolveUserGroupId(int $contextId, string $roleString): int
    {
        return (int)UserGroupLookup::userGroupForRole($contextId, $roleString)->id;
    }

    /**
     * Cypress-compatible password rule so both suites can use the same creds.
     * See lib/pkp/cypress/support/commands.js:20-31.
     */
    private function derivePassword(string $username): string
    {
        return $username === 'admin' ? 'admin' : $username . $username;
    }
}
