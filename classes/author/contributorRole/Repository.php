<?php

/**
 * @file classes/author/contributorRole/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to contributor roles
 */

namespace PKP\author\contributorRole;

use APP\core\Application;

class Repository
{
    // Add or edit. Use role id to edit. Use identifier and context id to add or edit.
    public function add(array $translations, ?string $identifier = null, ?int $contextId = null, ?int $roleId = null): int
    {
        return ContributorRole::updateOrCreate(
            [
                ...$roleId
                    ? ['contributor_role_id' => $roleId] /* Edit */
                    : ['context_id' => $contextId, 'contributor_role_identifier' => $identifier] /* Add/Edit */
            ],
            [
                'name' => $translations,
                ...($roleId && $identifier) /* Edit */
                    ? ['contributor_role_identifier' => $identifier]
                    : []
            ]
        )
        ->id;
    }

    public function delete(int $contextId = null, string $identifier = null, int $roleId = null): void
    {
        if ($roleId) {
            ContributorRole::query()
                ->withRoleId($roleId)
                ->delete();
            return;
        }
        ContributorRole::query()
            ->withContextId($contextId)
            ->withIdentifiers([$identifier])
            ->delete();
    }

    /**
     * Get localized, or all, context's contributor roles with settings in assoc: identifier => translation(s).
     */
    public function getByContextId(int $contextId, string $locale = null): array
    {
        return ContributorRole::query()
            ->withContextId($contextId)
            ->withSettings()
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_identifier => $locale ? $role->getLocalizedData('name', $locale) : $role->name
            ])
            ->toArray();
    }

    /**
     * Get localized, or all, context's contributor role with settings in assoc: identifier => translation(s).
     */
    public function getByContextIdAndIdentifiers(int $contextId, array $identifiers, string $locale = null): array
    {
        return ContributorRole::query()
            ->withContextId($contextId)
            ->withIdentifiers($identifiers)
            ->withSettings()
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_identifier => $locale ? $role->getLocalizedData('name', $locale) : $role->name
            ])
            ->toArray();
    }

    /**
     * Get context's contributor role with settings by identifier in assoc: identifier => translation(s).
     */
    public function getByRoleId(int $roleId, string $locale = null): array
    {
        return ContributorRole::query()
            ->withRoleId($roleId)
            ->withSettings()
            ->get()
            ->mapWithKeys(fn (ContributorRole $role) => [
                $role->contributor_role_identifier => $locale ? $role->getLocalizedData('name', $locale) : $role->name
            ])
            ->toArray();
    }
}
