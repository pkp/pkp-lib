<?php

/**
 * @file lib/pkp/classes/author/contributorRole/ContributorRole.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ContributorRole
 *
 * @brief ContributorRole model class
 */

namespace PKP\author\contributorRole;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PKP\author\creditContributorRole\CreditContributorRole;
use PKP\core\traits\ModelWithSettings;

class ContributorRole extends Model
{
    use ModelWithSettings;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'contributor_roles';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'contributor_role_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'contextId',
        'contributorRoleIdentifier',
    ];

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettingsTable
     */
    public function getSettingsTable(): string
    {
        return 'contributor_role_settings';
    }

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
            'contributor_role_id' => 'integer',
            'context_id' => 'integer',
            'contributor_role_identifier' => 'string',
        ];
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSchemaName
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getMultilingualProps
     */
    public function getMultilingualProps(): array
    {
        return array_merge($this->multilingualProps, ['name']);
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettings
     */
    public function getSettings(): array
    {
        return array_merge($this->settings, ['name']);
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
     * The contributor roles that belong to the contributor.
     */
    public function contributorRole(): BelongsTo
    {
        return $this->belongsTo(CreditContributorRole::class, 'contributor_role_id', 'contributor_role_id');
    }

    public function getData(): array
    {
        return [];
    }

    /**
     * Scope a query to include specific context id.
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }

    /**
     * Scope a query to include specific contributor role id.
     */
    public function scopeWithRoleId(Builder $query, int $roleId): Builder
    {
        return $query->where('contributor_role_id', $roleId);
    }

    /**
     * Scope a query to include specific contributor role identifiers.
     */
    public function scopeWithIdentifiers(Builder $query, array $identifiers): Builder
    {
        return $query->whereIn('contributor_role_identifier', $identifiers);
    }

    /**
     * Scope a query to only include entries for a specific contributor role id and locales
     */
    public function scopeWithSettings(Builder $query): Builder
    {
        return $query->whereExists(
            fn ($query) => $query
                ->select($this->primaryKey)
                ->from($this->getSettingsTable())
                ->whereColumn(
                    "{$this->getSettingsTable()}.{$this->primaryKey}",
                    "{$this->getTable()}.{$this->primaryKey}"
                )
        );
    }
}
