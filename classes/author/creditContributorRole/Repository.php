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
use PKP\author\creditRole\CreditRole;

class Repository
{
    public function addCreditRoles(array $newCreditRoles, int $contributorId): void
    {
        $creditRolesWithId = Arr::mapWithKeys(
            CreditRole::withCreditRoleIdentifiers(Arr::pluck($newCreditRoles, 'role'))->get()->toArray(),
            fn (array $cr): array => [$cr['creditRoleIdentifier'] => $cr['creditRoleId']]
        );

        $currentCreditRoles = CreditContributorRole::query()
            ->withContributorId($contributorId)
            ->whereNotNull('credit_role_id')
            ->select(['credit_contributor_role_id', 'credit_role_id'])
            ->get();

        $deleteIds = collect($currentCreditRoles)
            ->reject(fn (CreditContributorRole $roleItem) => in_array($roleItem->credit_role_id, $creditRolesWithId))
            ->pluck('credit_contributor_role_id')
            ->toArray();

        if ($deleteIds) {
            CreditContributorRole::query()
                ->withContributorId($contributorId)
                ->whereIn('credit_contributor_role_id', $deleteIds)
                ->delete();
        }

        foreach ($newCreditRoles as $rd) {
            $role = $rd['role'];
            if (!isset($creditRolesWithId[$role])) continue;
            CreditContributorRole::updateOrCreate(
                ['contributor_id' => $contributorId, 'credit_role_id' => $creditRolesWithId[$role]],
                ['credit_degree' => $rd['degree'] ?? null]
            );
        }
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
