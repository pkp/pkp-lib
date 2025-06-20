<?php

/**
 * @file classes/author/creditContributorRoles/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to credit roles
 */

namespace PKP\author\creditContributorRoles;

use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use PKP\author\creditRoles\CreditRoles;

class Repository
{
    public function addCreditRoles(array $creditContributorRoles, int $contributorId): void
    {
        $creditRoles = Arr::mapWithKeys(
            CreditRoles::withCreditRoleIdentifiers(Arr::pluck($creditContributorRoles, 'role'))->get()->toArray(),
            fn (array $cr): array => [$cr['creditRoleIdentifier'] => $cr['creditRoleId']]
        );

        $currentCreditRoles = CreditContributorRoles::query()
            ->withContributorId($contributorId)
            ->whereNotNull('credit_role_id')
            ->select(['credit_contributor_role_id', 'credit_role_id'])
            ->get();

        $deleteIds = collect($currentCreditRoles)
            ->reject(fn (CreditContributorRoles $roleItem) => in_array($roleItem->credit_role_id, $creditRoles))
            ->pluck('credit_contributor_role_id')
            ->toArray();

        if ($deleteIds) {
            CreditContributorRoles::query()
                ->withContributorId($contributorId)
                ->whereIn('credit_contributor_role_id', $deleteIds)
                ->delete();
        }

        foreach ($creditContributorRoles as $rd) {
            $role = $rd['role'];
            if (!isset($creditRoles[$role])) continue;
            CreditContributorRoles::updateOrCreate(
                ['contributor_id' => $contributorId, 'credit_role_id' => $creditRoles[$role]],
                ['credit_degree' => $rd['degree'] ?? null]
            );
        }
    }

    /**
     * Delete all contributor's credit roles and contributor roles.
     */
    public function deleteByContributorId(int $contributorId): void
    {
        CreditContributorRoles::query()->withContributorId($contributorId)->delete();
    }

    /**
     * Delete all contributor's credit roles.
     */
    public function deleteCreditRolesByContributorId(int $contributorId): void
    {
        CreditContributorRoles::query()
            ->withContributorId($contributorId)
            ->whereNotNull('credit_role_id')
            ->delete();
    }

    /**
     * Get all contributor's credit roles and contributor roles.
     */
    public function getByContributorId(int $contributorId): array
    {
        return CreditContributorRoles::query()
            ->withContributorId($contributorId)
            ->get()
            ->toArray();
    }

    /**
     * Get all contributor's credit roles.
     */
    public function getCreditRolesByContributorId(int $contributorId): array
    {
        return CreditContributorRoles::query()
            ->withContributorId($contributorId)
            ->whereNotNull('credit_contributor_roles.credit_role_id')
            ->leftJoin('credit_roles as cr', fn (JoinClause $join) => $join
                ->on('credit_contributor_roles.credit_role_id', '=', 'cr.credit_role_id'))
            ->select(['credit_role_identifier as role', 'credit_degree as degree'])
            ->get()
            ->toArray();
    }
}
