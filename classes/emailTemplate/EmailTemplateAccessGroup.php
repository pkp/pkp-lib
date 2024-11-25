<?php

/**
 * @file classes/emailTemplate/EmailTemplateAccessGroup.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2000-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class EmailTemplateAccessGroup
 *
 * @ingroup emailTemplate
 *
 * @brief Eloquent model for email template user group access
 */

namespace PKP\emailTemplate;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class EmailTemplateAccessGroup extends Model
{
    use HasCamelCasing;

    public $timestamps = false;
    protected $primaryKey = 'email_template_user_group_access_id';
    protected $table = 'email_template_user_group_access';
    protected $fillable = ['userGroupId', 'contextId', 'emailKey'];


    /**
     * Scope a query to only include email template access records for email templates with specific keys.
     */
    public function scopeWithEmailKey(Builder $query, ?array $keys): Builder
    {
        return $query->when(!empty($keys), function ($query) use ($keys) {
            return $query->whereIn('email_key', $keys);
        });
    }

    /**
     * Scope a query to only include email template access records that are related to a specific context ID.
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }

    /**
     * Scope a query to only include email template access records for specific user group IDs.
     */
    public function scopeWithGroupIds(Builder $query, array $ids): Builder
    {
        return $query->whereIn('user_group_id', $ids);
    }
}
