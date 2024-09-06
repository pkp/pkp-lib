<?php

/**
 * @file classes/query/Query.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Query
 *
 * @brief Class for Query.
 */

namespace PKP\query;

use APP\core\Application;
use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use PKP\db\DAO;
use PKP\note\Note;
use PKP\QueryParticipant\QueryParticipant;
use PKPApplication;

class Query extends Model
{
    use HasCamelCasing;

    const CREATED_AT = 'date_posted';
    const UPDATED_AT = 'date_modified';

	protected $table = 'queries';
	protected $primaryKey = 'query_id';

	protected $fillable = [
		'assocType', 'assocId', 'stageId', 'seq',
        'datePosted', 'dateModified', 'closed'
	];

    protected function casts(): array
    {
        return [
            'assocType' => 'int',
            'assocId' => 'int',
            'stageId' => 'int',
            'seq' => 'float',
            'datePosted' => 'datetime',
            'dateModified' => 'datetime',
            'closed' => 'boolean'
        ];
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn($value) => [$this->primaryKey => $value],
        );
    }

    /**
     * Accessor for user. Can be replaced with relationship once User is converted to an Eloquent Model.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: function () {
                return Repo::user()->get($this->queryParticipant->userId, true);
            },
        );
    }

    /**
     * Relationship to Query Participants. Can be replaced with Many-to-Many relationship once
     * User is converted to an Eloquent Model.
     */
    public function queryParticipants(): HasMany
    {
		return $this->hasMany(QueryParticipant::class);
	}

    // Scopes

    /**
     * Scope a query to only include queries with a specific assoc type and assoc ID.
     */
    public function scopeWithAssoc(Builder $query, int $assocType, int $assocId): Builder
    {
        return $query->where('assoc_type', $assocType)
            ->where('assoc_id', $assocId);
    }

    /**
     * Scope a query to only include queries with a specific stage ID.
     */
    public function scopeWithStageId(Builder $query, int $stageId): Builder
    {
        return $query->where('stage_id', $stageId);
    }

    /**
     * Scope a query to only include queries with a specific closed status.
     */
    public function scopeWithClosed(Builder $query, bool $closed): Builder
    {
        return $query->where('closed', $closed);
    }

    /**
     * Scope a query to only include queries with a specific user ID.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->queryParticipant->where('userId', $userId);
    }

    /**
     * Get the "head" (first) note for this query.
     *
     * @return Note
     */
    public function getHeadNote(): Note
    {
        return Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $this->id)
            ->withSort(Note::NOTE_ORDER_DATE_CREATED, DAO::SORT_DIRECTION_ASC)
            ->first();
    }
}
