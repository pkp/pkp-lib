<?php

/**
 * @file classes/userGroup/UserGroup.php
 *
 * Copyright (c) 2014-2014 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\UserGroup
 *
 * @brief Eloquent Model for UserGroup
 */

namespace PKP\userGroup;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PKP\core\PKPApplication;
use PKP\core\traits\ModelWithSettings;
use PKP\facades\Repo;
use PKP\plugins\Hook;
use PKP\services\PKPSchemaService;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\relationships\UserGroupStage;
use PKP\userGroup\relationships\UserUserGroup;

class UserGroup extends Model
{
    use ModelWithSettings;


    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'user_groups';

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'user_group_id';

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = ['userGroupId', 'id'];

    /**
     * Get the settings table name
     */
    public function getSettingsTable(): string
    {
        return 'user_group_settings';
    }

    /**
     * Get the schema name for the model
     */
    public static function getSchemaName(): ?string
    {
        return PKPSchemaService::SCHEMA_USER_GROUP;
    }

    /**
     * Get assigned stage IDs
     *
     * @return \Illuminate\Support\Collection
     */
    public function getAssignedStageIds()
    {
        return $this->userGroupStages()->pluck('stage_id');
    }

    /**
     * Define the relationship to UserUserGroups
     */
    public function userUserGroups(): HasMany
    {
        return $this->hasMany(UserUserGroup::class, 'user_group_id', 'user_group_id');
    }

    /**
     * Define the relationship to UserGroupStages
     */
    public function userGroupStages(): HasMany
    {
        return $this->hasMany(UserGroupStage::class, 'user_group_id', 'user_group_id');
    }

    protected function scopeWithContextIds(EloquentBuilder $builder, $contextIds): EloquentBuilder
    {
        if (empty($contextIds)) {
            return $builder;
        }

        if (!is_array($contextIds)) {
            $contextIds = [$contextIds];
        }

        $filteredIds = [];
        $siteWide = false;
        foreach ($contextIds as $contextId) {
            if ($contextId == PKPApplication::SITE_CONTEXT_ID) {
                $siteWide = true;
                continue;
            }

            $filteredIds[] = $contextId;
        }

        return $builder
            ->when(!empty($filteredIds), fn (EloquentBuilder $builder) => $builder->whereIn('context_id', $filteredIds))
            ->when($siteWide, fn (EloquentBuilder $builder) => $builder
                ->when(
                    empty($filteredIds),
                    fn (EloquentBuilder $builder) => $builder->whereNull('context_id'),
                    fn (EloquentBuilder $builder) => $builder->orWhereNull('context_id')
                ));
    }

    /**
     * Scope a query to filter by user group IDs.
     */
    protected function scopeWithUserGroupIds(EloquentBuilder $builder, $userGroupIds): EloquentBuilder
    {
        if (!is_array($userGroupIds)) {
            $userGroupIds = [$userGroupIds];
        }

        return $builder->whereIn('user_group_id', $userGroupIds);
    }

    /**
     * Scope a query to filter by role IDs.
     */
    protected function scopeWithRoleIds(EloquentBuilder $builder, $roleIds): EloquentBuilder
    {
        if (!is_array($roleIds)) {
            $roleIds = [$roleIds];
        }

        return $builder->whereIn('role_id', $roleIds);
    }

    /**
     * Scope a query to exclude certain role IDs.
     */
    protected function scopeExcludeRoles(EloquentBuilder $builder, array $excludeRoles): EloquentBuilder
    {
        return $builder->whereNotIn('role_id', $excludeRoles);
    }

    /**
     * Scope a query to filter by stage IDs.
     */
    protected function scopeWithStageIds(EloquentBuilder $builder, $stageIds): EloquentBuilder
    {
        if (!is_array($stageIds)) {
            $stageIds = [$stageIds];
        }
        return $builder->whereHas('userGroupStages', function (EloquentBuilder $q) use ($stageIds) {
            $q->whereIn('stage_id', $stageIds);
        });
    }

    /**
     * Scope a query to exclude role ids.
     */
    protected function scopeExcludeRoleIds(EloquentBuilder $builder, $roleIds): EloquentBuilder
    {
        if (!is_array($roleIds)) {
            $roleIds = [$roleIds];
        }
        return $builder->whereNotIn('role_id', $roleIds);
    }

    /**
     * Scope a query to filter by is_default.
     */
    protected function scopeIsDefault(EloquentBuilder $builder, bool $isDefault): EloquentBuilder
    {
        return $builder->where('is_default', $isDefault);
    }

    /**
     * Scope a query to filter by show_title.
     */
    protected function scopeShowTitle(EloquentBuilder $builder, bool $showTitle): EloquentBuilder
    {
        return $builder->where('show_title', $showTitle);
    }

    /**
     * Scope a query to filter by permit_self_registration.
     */
    protected function scopePermitSelfRegistration(EloquentBuilder $builder, bool $permitSelfRegistration): EloquentBuilder
    {
        return $builder->where('permit_self_registration', $permitSelfRegistration);
    }

    /**
     * Scope a query to filter by permit_metadata_edit.
     */
    protected function scopePermitMetadataEdit(EloquentBuilder $builder, bool $permitMetadataEdit): EloquentBuilder
    {
        return $builder->where('permit_metadata_edit', $permitMetadataEdit);
    }

    /**
     * Scope a query to filter by masthead.
     */
    protected function scopeMasthead(EloquentBuilder $builder, bool $masthead): EloquentBuilder
    {
        return $builder->where('masthead', $masthead);
    }

    /**
     * Scope a query to filter by user IDs.
     */
    protected function scopeWithUserIds(EloquentBuilder $builder, array $userIds): EloquentBuilder
    {
        return $builder->whereHas('userUserGroups', function (EloquentBuilder $q) use ($userIds) {
            $q->whereIn('user_id', $userIds);
        });
    }

    /**
     * Scope a query to filter by recommendOnly setting.
     */
    protected function scopeIsRecommendOnly(EloquentBuilder $builder, bool $isRecommendOnly): EloquentBuilder
    {
        return $builder->where('recommendOnly', $isRecommendOnly);
    }

    /**
     * Scope a query to filter by UserUserGroupStatus.
     */
    protected function scopeWithUserUserGroupStatus(EloquentBuilder $builder, string $status): EloquentBuilder
    {
        $currentDateTime = now();

        if ($status === 'active') {
            $builder->whereHas('userUserGroups', function (EloquentBuilder $q) use ($currentDateTime) {
                $q->where(function (EloquentBuilder $q) use ($currentDateTime) {
                    $q->where('date_start', '<=', $currentDateTime)
                        ->orWhereNull('date_start');
                })->where(function (EloquentBuilder $q) use ($currentDateTime) {
                    $q->where('date_end', '>', $currentDateTime)
                        ->orWhereNull('date_end');
                });
            });
        } elseif ($status === 'ended') {
            $builder->whereHas('userUserGroups', function (EloquentBuilder $q) use ($currentDateTime) {
                $q->whereNotNull('date_end')
                    ->where('date_end', '<=', $currentDateTime);
            });
        }
        // Implement other statuses if needed
        return $builder;
    }

    /**
     * Scope a query to order by role ID.
     */
    protected function scopeOrderByRoleId(EloquentBuilder $builder): EloquentBuilder
    {
        return $builder->orderBy('role_id', 'asc');
    }

    /**
     * Scope a query to order by user group ID.
     */
    protected function scopeOrderById(EloquentBuilder $builder): EloquentBuilder
    {
        return $builder->orderBy('user_group_id', 'asc');
    }

    /**
     * Scope a query to include active user count.
     */
    protected function scopeWithActiveUserCount(EloquentBuilder $builder, ?int $contextId = null): EloquentBuilder
    {
        $currentDateTime = now();

        $builder->select('user_groups.user_group_id')
            ->selectRaw('COUNT(user_user_groups.user_id) AS count')
            ->join('user_user_groups', 'user_user_groups.user_group_id', '=', 'user_groups.user_group_id')
            ->join('users', 'users.user_id', '=', 'user_user_groups.user_id')
            ->where('users.disabled', 0)
            ->where(function (EloquentBuilder $q) use ($currentDateTime) {
                $q->where('user_user_groups.date_start', '<=', $currentDateTime)
                    ->orWhereNull('user_user_groups.date_start');
            })
            ->where(function (EloquentBuilder $q) use ($currentDateTime) {
                $q->where('user_user_groups.date_end', '>', $currentDateTime)
                    ->orWhereNull('user_user_groups.date_end');
            })
            ->groupBy('user_groups.user_group_id');

        if ($contextId !== null) {
            $builder->where('user_groups.context_id', $contextId);
        }

        return $builder;
    }


    /**
     * Scope a query to filter by publication IDs.
     *
     * @param array<int> $publicationIds Array of publication IDs to filter by.
     */
    protected function scopeWithPublicationIds(EloquentBuilder $builder, array $publicationIds): EloquentBuilder
    {
        return $builder->join('authors as a', $this->table . '.' . $this->primaryKey, '=', 'a.user_group_id')
            ->whereIn('a.publication_id', $publicationIds);
    }

    /**
     * Find a UserGroup by ID and optional context ID.
     *
     */
    public static function findById(int $id, ?int $contextId = null): ?self
    {
        $query = self::where('user_group_id', $id);

        if ($contextId !== null) {
            $query->withContextIds([$contextId]);
        }

        return $query->first();
    }

    /**
     * Save the model to the database.
     *
     * @return bool
     *
     * @hook UserGroup::add [[$this]]
     * @hook UserGroup::edit [[$this]]
     */
    public function save(array $options = [])
    {
        $isNew = !$this->exists;

        $saved = parent::save($options);

        // Reload the model to ensure all relationships and settings are loaded
        $this->refresh();

        if ($isNew) {
            // This is a new record
            Hook::call('UserGroup::add', [$this]);
        } else {
            // This is an update
            Hook::call('UserGroup::edit', [$this]);
        }

        // Clear editorial masthead cache if the role is on the masthead
        if ($this->masthead) {
            Repo::userGroup()->forgetEditorialCache($this->contextId);
            Repo::userGroup()->forgetEditorialHistoryCache($this->contextId);
        }

        return $saved;
    }

    /**
     * Booted method to handle model events.
     *
     * @hook UserGroup::delete::before [[$userGroup]]
     */
    protected static function booted()
    {
        static::deleting(function ($userGroup) {
            // Equivalent to 'UserGroup::delete::before' hook
            Hook::call('UserGroup::delete::before', [$userGroup]);

            if ($userGroup->masthead) {
                Repo::userGroup()->forgetEditorialCache($userGroup->contextId);
                Repo::userGroup()->forgetEditorialHistoryCache($userGroup->contextId);
            }
        });

        static::deleted(function ($userGroup) {
            Hook::call('UserGroup::delete', [$userGroup]);
        });
    }

    /**
     * Define the relationship to StageAssignments.
     */
    public function stageAssignments(): HasMany
    {
        return $this->hasMany(StageAssignment::class, 'user_group_id', 'user_group_id');
    }

}
