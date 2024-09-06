<?php

/**
 * @file classes/query/QueryParticipant.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class QueryParticipant
 *
 * @brief Class for QueryParticipant.
 */

namespace PKP\QueryParticipant;

use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryParticipant extends Model
{
    use HasCamelCasing;

	protected $table = 'query_participants';
	protected $primaryKey = 'query_participant_id';
	public $timestamps = false;

	protected $fillable = [
		'queryId', 'userId'
	];

    protected function casts(): array
    {
        return [
            'queryId' => 'int',
            'userId' => 'int'
        ];
    }

	public function toQuery(): BelongsTo
	{
		return $this->belongsTo(Query::class);
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
                return Repo::user()->get($this->userId, true);
            },
        );
    }

    // Scopes

    /**
     * Scope a query to only include query participants with a specific user ID.
     */
    // TODO check if this is needed on the query participant class
    public function scopeWithQueryId(Builder $query, int $queryId): Builder
    {
        return $query->where('queryId', $queryId);
    }

    /**
     * Scope a query to only include query participants with a specific user ID.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('userId', $userId);
    }
}
