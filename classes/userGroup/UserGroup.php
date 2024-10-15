<?php

/**
 * @file classes/userGroup/UserGroup.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userGroup\UserGroup
 *
 * @brief Eloquent Model for UserGroup
 */

namespace PKP\userGroup;

use Illuminate\Database\Eloquent\Model;
use PKP\core\traits\ModelWithSettings;
use Eloquence\Behaviours\HasCamelCasing;
use PKP\stageAssignment\StageAssignment;
use PKP\userGroup\relationships\UserUserGroup;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PKP\facades\Locale;
use PKP\userGroup\relationships\UserGroupStage;
use Illuminate\Database\Query\Builder;

class UserGroup extends Model
{
    use ModelWithSettings;
    use HasCamelCasing;

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
        'context_id',
        'role_id',
        'is_default',
        'show_title',
        'permit_self_registration',
        'permit_metadata_edit',
        'masthead',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'role_id' => 'integer',
            'is_default' => 'boolean',
            'show_title' => 'boolean',
            'permit_self_registration' => 'boolean',
            'permit_metadata_edit' => 'boolean',
            'masthead' => 'boolean',
            // multilingual attributes will be handled through accessors and mutators
        ];
    }

    /**
     * List of attributes associated with the settings
     *
     * @var array
     */
    protected array $settings = [
        'name',
        'abbrev',
        'nameLocaleKey',
        'abbrevLocaleKey',
    ];

    /**
     * The list of multilingual attributes.
     *
     * @var array
     */
    protected array $multilingualProps = [
        'name',
        'abbrev',
    ];

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
        return 'userGroup';
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
     * setAttribute method for multilingual attributes
     *
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->settings)) {
            // it's a settings attribute
            if (in_array($key, $this->multilingualProps)) {
                // and it's a multilingual setting
                if (is_string($value)) {
                    $value = $this->localizeNonLocalizedData($value);
                }
            }
            $this->settings[$key] = $value;
            return $this;
        }

        return parent::setAttribute($key, $value);
    }

    /**
     * Override getAttribute to handle multilingual attributes.
     */
    public function getAttribute($key)
    {
        if (in_array($key, $this->settings)) {
            // it's a settings attribute
            return $this->settings[$key] ?? null;
        }

        return parent::getAttribute($key);
    }

    /**
     * Wraps a non localized value with the default locale
     *
     * @param string $value the non localized value
     * @return array array with the default locale as the key and the value
     */
    protected function localizeNonLocalizedData(string $value): array
    {
        return [Locale::getLocale() => $value];
    }

    /**
     * Define the relationship to StageAssignments
     */
    public function stageAssignments(): HasMany
    {
        return $this->hasMany(StageAssignment::class, 'user_group_id', 'user_group_id');
    }

    /**
     * Define the relationship to UserUserGroups
     */
    public function userUserGroups(): HasMany
    {
        return $this->hasMany(UserUserGroup::class, 'user_group_id', 'user_group_id');
    }

    /**
     * Define the relationship to UserUserGroups
     */
    public function userGroupStages(): HasMany
    {
        return $this->hasMany(UserGroupStage::class, 'user_group_id', 'user_group_id');
    }

    /**
     * Scope a query to filter by context IDs.
     */
    public function scopeWithContextIds(Builder $query, ?array $contextIds)
    {
        if ($contextIds !== null) {
            $query->whereIn('context_id', $contextIds);
        }
        return $query;
    }

    /**
     * Scope a query to filter by user group IDs.
     */
    public function scopeWithUserGroupIds(Builder $query, ?array $userGroupIds)
    {
        if ($userGroupIds !== null) {
            $query->whereIn('user_group_id', $userGroupIds);
        }
        return $query;
    }

    /**
     * Scope a query to filter by role IDs.
     */
    public function scopeWithRoleIds(Builder $query, ?array $roleIds)
    {
        if ($roleIds !== null) {
            $query->whereIn('role_id', $roleIds);
        }
        return $query;
    }

    /**
     * Scope a query to exclude certain role IDs.
     */
    public function scopeExcludeRoles(Builder $query, ?array $excludeRoles)
    {
        if ($excludeRoles !== null) {
            $query->whereNotIn('role_id', $excludeRoles);
        }
        return $query;
    }

    /**
     * Scope a query to filter by stage IDs.
     */
    public function scopeWithStageIds(Builder $query, ?array $stageIds)
    {
        if ($stageIds !== null) {
            $query->whereHas('userGroupStages', function (Builder $q) use ($stageIds) {
                $q->whereIn('stage_id', $stageIds);
            });
        }
        return $query;
    }

    /**
     * Scope a query to filter by is_default.
     */
    public function scopeIsDefault(Builder $query, ?bool $isDefault)
    {
        if ($isDefault !== null) {
            $query->where('is_default', $isDefault);
        }
        return $query;
    }

    /**
     * Scope a query to filter by show_title.
     */
    public function scopeShowTitle(Builder $query, ?bool $showTitle)
    {
        if ($showTitle !== null) {
            $query->where('show_title', $showTitle);
        }
        return $query;
    }

    /**
     * Scope a query to filter by permit_self_registration.
     */
    public function scopePermitSelfRegistration(Builder $query, ?bool $permitSelfRegistration)
    {
        if ($permitSelfRegistration !== null) {
            $query->where('permit_self_registration', $permitSelfRegistration);
        }
        return $query;
    }

    /**
     * Scope a query to filter by permit_metadata_edit.
     */
    public function scopePermitMetadataEdit(Builder $query, ?bool $permitMetadataEdit)
    {
        if ($permitMetadataEdit !== null) {
            $query->where('permit_metadata_edit', $permitMetadataEdit);
        }
        return $query;
    }

    /**
     * Scope a query to filter by masthead.
     */
    public function scopeMasthead(Builder $query, ?bool $masthead)
    {
        if ($masthead !== null) {
            $query->where('masthead', $masthead);
        }
        return $query;
    }

    /**
     * Scope a query to filter by user IDs.
     */
    public function scopeWithUserIds(Builder $query, ?array $userIds)
    {
        if ($userIds !== null) {
            $query->whereHas('userUserGroups', function (Builder $q) use ($userIds) {
                $q->whereIn('user_id', $userIds);
            });
        }
        return $query;
    }

    /**
     * Scope a query to filter by recommendOnly setting.
     */
    public function scopeIsRecommendOnly(Builder $query, ?bool $isRecommendOnly)
    {
        if ($isRecommendOnly !== null) {
            $query->whereHas('settings', function (Builder $q) use ($isRecommendOnly) {
                $q->where('setting_name', 'recommendOnly')
                  ->where('setting_value', $isRecommendOnly);
            });
        }
        return $query;
    }

    /**
     * Scope a query to filter by UserUserGroupStatus.
     */
    public function scopeWithUserUserGroupStatus(Builder $query, string $status)
    {
        $currentDateTime = now();

        if ($status === 'active') {
            $query->whereHas('userUserGroups', function (Builder $q) use ($currentDateTime) {
                $q->where(function ($q) use ($currentDateTime) {
                    $q->where('date_start', '<=', $currentDateTime)
                      ->orWhereNull('date_start');
                })->where(function ($q) use ($currentDateTime) {
                    $q->where('date_end', '>', $currentDateTime)
                      ->orWhereNull('date_end');
                });
            });
        } elseif ($status === 'ended') {
            $query->whereHas('userUserGroups', function (Builder $q) use ($currentDateTime) {
                $q->whereNotNull('date_end')
                  ->where('date_end', '<=', $currentDateTime);
            });
        }
        // Implement other statuses if needed
        return $query;
    }

    /**
     * Scope a query to order by role ID.
     */
    public function scopeOrderByRoleId(Builder $query)
    {
        return $query->orderBy('role_id', 'asc');
    }

    /**
     * Scope a query to order by user group ID.
     */
    public function scopeOrderById(Builder $query)
    {
        return $query->orderBy('user_group_id', 'asc');
    }
}
