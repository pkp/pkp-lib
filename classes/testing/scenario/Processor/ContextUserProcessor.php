<?php

/**
 * @file classes/testing/scenario/Processor/ContextUserProcessor.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContextUserProcessor
 *
 * @brief Assigns existing users (from the bootstrap baseline) to roles
 *        in the freshly-created scratch context.
 *
 * Unlike bootstrap's UserProcessor, this does not create new User rows
 * — it maps already-bootstrapped usernames to the scratch context's
 * default UserGroups. Specs typically list at least one user with role
 * 'manager' so the test can log in and exercise settings pages.
 */

namespace PKP\testing\scenario\Processor;

use APP\facades\Repo;
use PKP\testing\scenario\ScenarioContext;
use PKP\testing\scenario\ScenarioProcessor;
use PKP\testing\scenario\UserGroupLookup;

class ContextUserProcessor implements ScenarioProcessor
{
    public function appliesTo(array $spec): bool
    {
        return !empty($spec['users']);
    }

    public function run(array $spec, ScenarioContext $ctx): array
    {
        $contextId = $ctx->journalId($spec['path']);

        foreach ($spec['users'] as $userSpec) {
            $user = $ctx->userByUsername($userSpec['username']);
            foreach ($userSpec['roles'] as $role) {
                $userGroup = UserGroupLookup::userGroupForRole($contextId, $role);
                Repo::userGroup()->assignUserToGroup($user->getId(), (int)$userGroup->id);
            }
        }

        return [];
    }
}
