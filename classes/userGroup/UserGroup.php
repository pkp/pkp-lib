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
 use PKP\core\traits\ModelWithSettings;
 use PKP\stageAssignment\StageAssignment;
 use PKP\userGroup\relationships\UserUserGroup;
 use PKP\userGroup\relationships\UserGroupStage;
 use PKP\facades\Locale;
 use PKP\plugins\Hook;
 use PKP\facades\Repo;
 use PKP\services\PKPSchemaService;
 use PKP\core\PKPApplication;
 use APP\core\Application;


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
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'contextId',
        'roleId',
        'isDefault',
        'showTitle',
        'permitSelfRegistration',
        'permitMetadataEdit',
        'permitSettings' => 'boolean',
        'masthead',
    ];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'contextId' => 'integer',
            'roleId' => 'integer',
            'isDefault' => 'boolean',
            'showTitle' => 'boolean',
            'permitSelfRegistration' => 'boolean',
            'permitMetadataEdit' => 'boolean',
            'masthead' => 'boolean',
            'permitSettings' => 'boolean',
            // multilingual attributes will be handled through accessors and mutators
        ];
    }

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
     * Define multilingual properties
     */
    public function getMultilingualProps(): array
    {
        return [
            'name',
            'namePlural',
            'abbrev',
        ];
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
    
        return $builder->whereIn('context_id', $contextIds);
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
     * Ensure casts are string values.
     *
     * @param array $casts
     * @return array
     */
    protected function ensureCastsAreStringValues($casts): array
    {
        return array_map(fn($cast) => (string) $cast, $casts);
    }


    /**
     * Scope a query to filter by publication IDs.
     *
     * @param EloquentBuilder $builder
     * @param array<int> $publicationIds Array of publication IDs to filter by.
     * @return EloquentBuilder
     */
    protected function scopeWithPublicationIds(EloquentBuilder $builder, array $publicationIds): EloquentBuilder
    {
        return $builder->whereHas('userUserGroups', function (EloquentBuilder $q) use ($publicationIds) {
            $q->whereIn('publication_id', $publicationIds);
        });
    }

    /**
     * Set the name attribute.
     *
     * @param mixed $value
     * @return void
     */
    public function setNameAttribute($value): void
    {
        if (is_string($value)) {
            $value = $this->localizeNonLocalizedData($value);
        }
        $this->setData('name', $value);
    }

    /**
     * Set the abbrev attribute.
     *
     * @param mixed $value
     * @return void
     */
    public function setAbbrevAttribute($value): void
    {
        if (is_string($value)) {
            $value = $this->localizeNonLocalizedData($value);
        }
        $this->setData('abbrev', $value);
    }

    /**
     * Localize non-localized data.
     *
     * @param string $value
     * @return array
     */
    protected function localizeNonLocalizedData(string $value): array
    {
        return [Locale::getLocale() => $value];
    }

    /**
     * Find a UserGroup by ID and optional context ID.
     *
     * @param int $id
     * @param int|null $contextId
     * @return self|null
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
     * @param array $options
     * @return bool
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
     * @return void
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
