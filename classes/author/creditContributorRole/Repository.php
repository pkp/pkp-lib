<?php

/**
 * @file classes/author/creditContributorRole/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to credit roles
 */

namespace PKP\author\creditContributorRole;

use Illuminate\Support\Arr;
use PKP\author\contributorRole\ContributorRole;
use PKP\author\creditRole\CreditRole;

class Repository
{
    /** Add contributor roles for a contributor */
    public function addContributorRoles(array $contributorRoles, int $contributorId): void
    {
        $roleIds = collect($contributorRoles)
            ->map(fn (ContributorRole $role) => $role->contributorRoleId);

        // Disallow removal of all roles (see the code below; delete and create/update)
        if ($roleIds->isEmpty()) {
            return;
        }

        // Delete old roles
        CreditContributorRole::query()
            ->withContributorId($contributorId)
            ->whereNotNull('contributor_role_id')
            ->whereNotIn('contributor_role_id', $roleIds)
            ->delete();

        $roleIds->each(fn (int $roleId) =>
            CreditContributorRole::updateOrCreate(
                ['contributor_id' => $contributorId, 'contributor_role_id' => $roleId],
                []
            ));
    }

    /** Add CRediT roles for a contributor */
    public function addCreditRoles(array $newRoles, int $contributorId): void
    {
        $identifiersWithIds = CreditRole::withCreditRoleIdentifiers(Arr::pluck($newRoles, 'role'))
            ->get()
            ->mapWithKeys(fn (CreditRole $role) => [$role->creditRoleIdentifier => $role->creditRoleId]);

        // Delete old roles
        CreditContributorRole::query()
            ->withContributorId($contributorId)
            ->whereNotNull('credit_role_id')
            ->whereNotIn('credit_role_id', $identifiersWithIds)
            ->delete();

        foreach ($newRoles as $rd) {
            if ($roleId = $identifiersWithIds->get($rd['role'])) {
                CreditContributorRole::updateOrCreate(
                    ['contributor_id' => $contributorId, 'credit_role_id' => $roleId],
                    ['credit_degree' => $rd['degree'] ?? null]
                );
            }
        }
    }

    /**
     * Get all contributor's contributor roles as objects.
     */
    public function getContributorRolesByContributorId(int $contributorId): array
    {
        return ContributorRole::query()
            ->whereHas(
                'contributorRole',
                fn ($query) => $query->withContributorId($contributorId)
            )
            ->get()
            ->all();
    }

    /**
     * Get all contributor's credit roles.
     */
    public function getCreditRolesByContributorId(int $contributorId): array
    {
        return CreditContributorRole::query()
            ->withContributorId($contributorId)
            ->withCreditRoles()
            ->select(['credit_role_identifier as role', 'credit_degree as degree'])
            ->get()
            ->toArray();
    }
}
