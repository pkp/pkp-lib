<?php

/**
 * @file lib/pkp/classes/author/creditRole/CreditRole.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class CreditRole
 *
 * @brief CreditRole model class
 */

namespace PKP\author\creditRole;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Builder;

class CreditRole extends Model
{
    use HasCamelCasing;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'credit_roles';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'credit_role_id';

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
            'credit_role_id' => 'integer',
            'credit_role_identifier' => 'string',
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
     * Scope a query to include specific credit roles.
     */
    public function scopeWithCreditRoleIdentifiers(Builder $query, array $creditRoles): Builder
    {
        return $query->whereIn('credit_role_identifier', $creditRoles);
    }
}
