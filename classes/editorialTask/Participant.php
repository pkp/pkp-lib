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

namespace PKP\editorialTask;

use APP\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PKP\user\User;

class Participant extends Model
{
    use HasCamelCasing;

    protected $table = 'edit_task_participants';
    protected $primaryKey = 'edit_task_participant_id';
    public $timestamps = false;

    protected $guarded = [
        'editTaskParticipantId', 'id'
    ];

    protected $fillable = [
        'editTaskId', 'userId', 'isResponsible'
    ];

    protected function casts(): array
    {
        return [
            'editTaskId' => 'int',
            'userId' => 'int',
            'isResponsible' => 'bool',
        ];
    }
    public function toTask(): BelongsTo
    {
        return $this->belongsTo(EditorialTask::class, 'edit_task_id', 'edit_task_id');
    }

    /**
     * Accessor and Mutator for primary key => id
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
            set: fn ($value) => [$this->primaryKey => $value],
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
     * Scope a query to only include query participants with a specific query ID.
     */
    public function scopeWithTaskId(Builder $query, int $taskId): Builder
    {
        return $query->where('edit_task_id', $taskId);
    }

    /**
     * Scope a query to only include query participants with a specific user ID.
     */
    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }
}
