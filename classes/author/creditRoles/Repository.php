<?php

/**
 * @file classes/author/creditRoles/Repository.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to manage actions related to credit roles
 */

namespace PKP\author\creditRoles;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Repository
{
    public function add(array $creditRoles, int $contributorId): void
    {
        $currentCreditRoles = CreditRoles::query()
            ->withContributorId($contributorId)
            ->select(['contributor_credit_role_id', 'role'])
            ->get();

        $deleteIds = collect($currentCreditRoles)
            ->reject(fn (CreditRoles $roleItem) => in_array($roleItem->role, Arr::pluck($creditRoles, 'role')))
            ->pluck('contributor_credit_role_id')
            ->toArray();

        if ($deleteIds) {
            DB::table('contributor_credit_roles')
                ->whereIn('contributor_credit_role_id', $deleteIds)
                ->delete();
        }

        foreach ($creditRoles as ['role' => $role, 'degree' => $degree]) {
            CreditRoles::updateOrCreate(
                ['contributor_id' => $contributorId, 'role' => $role],
                ['degree' => $degree]
            );
        }
    }

    /**
     * Delete all contributor's creditRoles.
     */
    public function deleteByContributorId(int $contributorId): void
    {
        CreditRoles::query()->withContributorId($contributorId)->delete();
    }

    public function getByContributorId(int $contributorId): array
    {
        return CreditRoles::query()
            ->withContributorId($contributorId)
            ->select(['role', 'degree'])
            ->get()
            ->toArray();
    }
}
