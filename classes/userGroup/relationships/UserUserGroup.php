<?php

/**
 * @file classes/userGroup/relationships/UserUserGroup.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\relationships\UserUserGroup
 *
 * @brief UserUserGroup metadata class.
 */

namespace PKP\userGroup\relationships;

use PKP\core\Core;
use APP\facades\Repo;
use PKP\userGroup\UserGroup;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserUserGroup extends \Illuminate\Database\Eloquent\Model
{
    use HasCamelCasing;

    public $timestamps = false;
    public $incrementing = false;
    protected $primaryKey = null;
    protected $fillable = ['userGroupId', 'userId', 'dateStart', 'dateEnd', 'masthead'];
    protected $casts = [
        'dateStart' => 'datetime',
        'dateEnd' => 'datetime',
    ];

    public function user(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => Repo::user()->get($attributes['user_id']),
            set: fn ($value) => $value->getId()
        );
    }

    /**
     * Define the relationship to UserGroup
     */
    public function userGroup(): BelongsTo
    {
        return $this->belongsTo(UserGroup::class, 'user_group_id', 'user_group_id');
    }

    public function scopeWithUserId(Builder $query, int $userId): Builder
    {
        return $query->where('user_user_groups.user_id', $userId);
    }

    public function scopeWithUserGroupIds(Builder $query, array $userGroupIds): Builder
    {
        return $query->whereIn('user_user_groups.user_group_id', $userGroupIds);
    }

    public function scopeWithActive(Builder $query): Builder
    {
        $currentDateTime = Core::getCurrentDate();
        return $query->where(
            fn (Builder $query) =>
            $query->where('user_user_groups.date_start', '<=', $currentDateTime)
                ->orWhereNull('user_user_groups.date_start')
        )
            ->where(
                fn (Builder $query) =>
                $query->where('user_user_groups.date_end', '>', $currentDateTime)
                    ->orWhereNull('user_user_groups.date_end')
            );
    }

    public function scopeWithEnded(Builder $query): Builder
    {
        $currentDateTime = Core::getCurrentDate();
        return $query->whereNotNull('user_user_groups.date_end')
            ->where('user_user_groups.date_end', '<=', $currentDateTime);
    }

    public function scopeWithContextId(Builder $query, ?int $contextId): Builder
    {
        return $query
            ->join('user_groups as ug', 'user_user_groups.user_group_id', '=', 'ug.user_group_id')
            ->whereRaw('COALESCE(ug.context_id, 0) = ?', [(int) $contextId]);
    }

    public function scopeWithMasthead(Builder $query): Builder
    {
        return $query->where('user_user_groups.masthead', 1);
    }

    public function scopeSortBy(Builder $query, string $column, ?string $direction = 'asc')
    {
        return $query->orderBy('user_user_groups.' . $column, $direction);
    }
}
