<?php

/**
 * @file lib/pkp/classes/author/creditContributorRole/CreditContributorRole.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreditContributorRole
 *
 * @brief CreditContributorRole model class
 */

namespace PKP\author\creditContributorRole;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

class CreditContributorRole extends Model
{
    use HasCamelCasing;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'credit_contributor_roles';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'credit_contributor_role_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'creditRoleId',
        'contributorRoleId',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasTimestamps::$timestamps
     */
    public $timestamps = false;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'contributor_id' => 'integer',
            'credit_role_id' => 'integer',
            'credit_degree' => 'string',
            'contributor_role_id' => 'integer',
        ];
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
        )->shouldCache();
    }

    /**
     * Scope a query to only include credit and contributor roles with a specific contributor id.
     */
    public function scopeWithContributorId(Builder $query, int $contributorId): Builder
    {
        return $query->where('contributor_id', $contributorId);
    }

    /**
     * Scope a query to only include credit roles with a specific contributor id.
     */
    public function scopeWithCreditRoles(Builder $query): Builder
    {
        return $query
            ->whereNotNull('credit_contributor_roles.credit_role_id')
            ->leftJoin('credit_roles as cr', fn (JoinClause $join) => $join
                ->on('credit_contributor_roles.credit_role_id', '=', 'cr.credit_role_id'));
    }
}
