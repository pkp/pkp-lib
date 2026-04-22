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
use PKP\security\Validation;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\userGroup\UserGroup;

class UserProcessor implements ScenarioProcessor
{
    /**
     * Role strings accepted in the spec → nameLocaleKey of the default
     * UserGroup installed for every OJS journal.
     */
    private const ROLE_TO_GROUP_KEY = [
        'manager' => 'default.groups.name.manager',
        'editor' => 'default.groups.name.editor',
        'productionEditor' => 'default.groups.name.productionEditor',
        'sectionEditor' => 'default.groups.name.sectionEditor',
        'guestEditor' => 'default.groups.name.guestEditor',
        'copyeditor' => 'default.groups.name.copyeditor',
        'layoutEditor' => 'default.groups.name.layoutEditor',
        'proofreader' => 'default.groups.name.proofreader',
        'designer' => 'default.groups.name.designer',
        'indexer' => 'default.groups.name.indexer',
        'marketing' => 'default.groups.name.marketing',
        'funding' => 'default.groups.name.funding',
        'author' => 'default.groups.name.author',
        'translator' => 'default.groups.name.translator',
        'reviewer' => 'default.groups.name.externalReviewer',
        'reader' => 'default.groups.name.reader',
        'subscriptionManager' => 'default.groups.name.subscriptionManager',
        'editorialBoardMember' => 'default.groups.name.editorialBoardMember',
    ];

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

        $user = Repo::user()->newDataObject([
            'username' => $username,
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
        if (!isset(self::ROLE_TO_GROUP_KEY[$roleString])) {
            throw new \InvalidArgumentException(
                "Unknown role '{$roleString}'. Known roles: "
                . implode(', ', array_keys(self::ROLE_TO_GROUP_KEY))
            );
        }

        $nameKey = self::ROLE_TO_GROUP_KEY[$roleString];
        $userGroup = UserGroup::withContextIds($contextId)
            ->where('nameLocaleKey', $nameKey)
            ->first();

        if (!$userGroup) {
            throw new \RuntimeException(
                "Could not find default user group '{$nameKey}' for context {$contextId}. "
                . "Was the journal created through the standard service (which installs userGroups.xml)?"
            );
        }

        return (int)$userGroup->id;
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
