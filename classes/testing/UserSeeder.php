<?php

/**
 * @file classes/testing/UserSeeder.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserSeeder
 *
 * @brief Creates users and enrols them in a context's default user groups the
 * way registration + the enrolment UI do (real repositories, real hooks).
 *
 * Two phases: parse(Spec) reads every key (so unknown keys 400 before any
 * write); seed(Context, plan) performs the mutations. Role keys are resolved
 * against each group's stored nameLocaleKey, so the vocabulary is exactly the
 * set of default groups the app ships (`default.groups.name.<key>` → key). A
 * key the app does not ship throws a SpecException that lists the whole set.
 */

namespace PKP\testing;

use APP\core\Application;
use APP\facades\Repo;
use PKP\context\Context;
use PKP\core\Core;
use PKP\db\DAORegistry;
use PKP\security\Role;
use PKP\security\Validation;
use PKP\user\User;
use PKP\userGroup\UserGroup;

class UserSeeder
{
    /**
     * Read one user spec into a plain plan. $structureKey names the
     * sub-editor assignment list ('sections' or 'series').
     */
    public function parse(Spec $spec, string $structureKey): array
    {
        $username = (string) $spec->require('username');
        $roles = $spec->require('roles');
        if (!is_array($roles) || $roles === []) {
            throw new SpecException("{$spec->path}.roles", 'Each user needs a non-empty roles list');
        }
        // ORCID iD fixture state (U4): a connected iD is only reachable
        // through ORCID's own OAuth sign-in, which can never complete in the
        // test env (outbound HTTP fails fast at the dead-port `[proxy]` in
        // config.test.inc.php, and the sandbox ORCID credentials are dummies)
        // — so the verified/unauthenticated states are seeded directly
        // (`orcid` + `orcidIsVerified`, the same user_settings rows the OAuth
        // landing stores, minus the token fields — deliberate deviation,
        // parity ledger 2026-08-07).
        $orcid = $spec->get('orcid');
        $orcidIsVerified = (bool) $spec->get('orcidIsVerified', false);
        if ($orcidIsVerified && !$orcid) {
            throw new SpecException("{$spec->path}.orcidIsVerified", 'orcidIsVerified requires an orcid value');
        }
        return [
            'specPath' => $spec->path,
            'username' => $username,
            'roles' => $roles,
            'givenName' => $spec->get('givenName', $username),
            'familyName' => $spec->get('familyName'),
            'email' => (string) $spec->get('email', "{$username}@mail.test"),
            // Deterministic password rule: explicit password honored, else the
            // username doubled (data/users.js getPassword).
            'password' => (string) $spec->get('password', $username . $username),
            'structures' => (array) $spec->get($structureKey, []),
            'structureKey' => $structureKey,
            'orcid' => $orcid !== null ? (string) $orcid : null,
            'orcidIsVerified' => $orcidIsVerified,
        ];
    }

    /**
     * Execute a parsed plan against a context.
     *
     * @param callable(string): ?int $resolveStructureId identifier → section/series id
     */
    public function seed(Context $context, array $plan, callable $resolveStructureId): User
    {
        $locale = $context->getPrimaryLocale();
        $username = $plan['username'];

        $user = Repo::user()->getByUsername($username, true);
        if (!$user) {
            $user = Repo::user()->newDataObject();
            $user->setUsername($username);
            foreach ($this->asLocalized($plan['givenName'], $locale) as $nameLocale => $value) {
                $user->setGivenName($value, $nameLocale);
            }
            foreach ($this->asLocalized($plan['familyName'], $locale) as $nameLocale => $value) {
                $user->setFamilyName($value, $nameLocale);
            }
            $user->setEmail($plan['email']);
            $user->setDateRegistered(Core::getCurrentDate());
            $user->setInlineHelp(1);
            $user->setPassword(Validation::encryptCredentials($username, $plan['password']));
            Repo::user()->add($user);
        }

        if (($plan['orcid'] ?? null) !== null) {
            $user->setOrcid($plan['orcid']);
            $user->setData('orcidIsVerified', $plan['orcidIsVerified']);
            Repo::user()->edit($user);
        }

        $assignedGroups = [];
        foreach ($plan['roles'] as $i => $roleKey) {
            $userGroup = $this->resolveUserGroup($context, (string) $roleKey, "{$plan['specPath']}.roles.{$i}");
            // Explicit masthead = true: every admin-driven enrolment path
            // ends with an explicit boolean, and the default is "appears on
            // the masthead" — the users-grid edit form pre-checks every
            // masthead checkbox and sweeps the value on save
            // (UserForm::saveUserGroupAssignments ~188-194, verified live
            // 2026-08-23: grid enrolment writes masthead = 1), the reviewer
            // enrol forms pass true, and the invitation flow stores the
            // inviter's explicit choice (reviewers forced true). NULL is only
            // left by self-registration — not the path the roster mirrors.
            // Whether a user actually shows on the public masthead is still
            // gated by the GROUP's own masthead flag, exactly as in the app.
            Repo::userGroup()->assignUserToGroup($user->getId(), $userGroup->id, null, null, true);
            $assignedGroups[] = $userGroup;
        }

        // Sub-editor assignments (sections OJS/OPS, series OMP) — the same
        // subeditor_submission_group row the section/series form writes.
        if ($plan['structures']) {
            $subEditorGroup = $this->pickSubEditorGroup($assignedGroups);
            if (!$subEditorGroup) {
                throw new SpecException(
                    "{$plan['specPath']}.{$plan['structureKey']}",
                    "User \"{$username}\" has no sub-editor-capable role for a {$plan['structureKey']} assignment"
                );
            }
            $subEditorsDao = DAORegistry::getDAO('SubEditorsDAO'); /** @var \PKP\context\SubEditorsDAO $subEditorsDao */
            foreach ($plan['structures'] as $identifier) {
                $structureId = $resolveStructureId((string) $identifier);
                if (!$structureId) {
                    throw new SpecException(
                        "{$plan['specPath']}.{$plan['structureKey']}",
                        "Unknown {$plan['structureKey']} identifier \"{$identifier}\" for user \"{$username}\""
                    );
                }
                $subEditorsDao->insertEditor(
                    $context->getId(),
                    $structureId,
                    $user->getId(),
                    Application::ASSOC_TYPE_SECTION,
                    $subEditorGroup->id
                );
            }
        }

        return $user;
    }

    /**
     * Resolve a role key (`manager`, `sectionEditor`, `externalReviewer`, …)
     * against the context's default groups by nameLocaleKey.
     */
    public function resolveUserGroup(Context $context, string $roleKey, string $specKey): UserGroup
    {
        $groups = UserGroup::withContextIds([$context->getId()])->get();
        foreach ($groups as $group) {
            if ($group->nameLocaleKey === "default.groups.name.{$roleKey}") {
                return $group;
            }
        }
        $available = $groups
            ->map(fn (UserGroup $group) => preg_replace('/^default\.groups\.name\./', '', (string) $group->nameLocaleKey))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->join(', ');
        throw new SpecException($specKey, "Unknown role key \"{$roleKey}\". This app's keys: {$available}");
    }

    /** Wrap a bare string under the given locale; pass locale maps through. */
    private function asLocalized(mixed $value, string $locale): array
    {
        if ($value === null) {
            return [];
        }
        return is_array($value) ? $value : [$locale => $value];
    }

    /**
     * The group a sub-editor assignment rides on: a sub-editor-role group
     * first, else an editor (manager-role) group.
     *
     * @param UserGroup[] $groups
     */
    private function pickSubEditorGroup(array $groups): ?UserGroup
    {
        foreach ($groups as $group) {
            if ((int) $group->roleId === Role::ROLE_ID_SUB_EDITOR) {
                return $group;
            }
        }
        foreach ($groups as $group) {
            if ((int) $group->roleId === Role::ROLE_ID_MANAGER) {
                return $group;
            }
        }
        return null;
    }
}
