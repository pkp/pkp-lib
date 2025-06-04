<?php

/**
 * @file classes/UserComment/relationships/UserCommentReport.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserCommentReport
 *
 * @ingroup UserComment
 *
 * @brief Model class describing a user comment report in the system.
 */

namespace PKP\userComment\relationships;

use App\facades\Repo;
use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PKP\userComment\UserComment;

/**
 * @method static EloquentBuilder withCommentIds(array $commentIds) Filter reports by comment IDs.
 * @method static EloquentBuilder withReportIds(array $reportIds) Filter reports by report IDs.
 */
class UserCommentReport extends Model
{
    use HasCamelCasing;

    protected $table = 'user_comment_reports';
    protected $primaryKey = 'user_comment_report_id';
    public $timestamps = true;

    protected $guarded = [
        'userCommentReportId',
    ];

    /**
     * Get the primary key of the model as 'id' property.
     */
    protected function id(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => $attributes[$this->primaryKey] ?? null,
        );
    }

    /**
     * Get the comment that the report belongs to.
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(UserComment::class, 'user_comment_id', 'user_comment_id');
    }

    /**
     * Get the user that created the report.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::user()->get($this->userId, true),
        )->shouldCache();
    }

    // Scopes

    /**
     * Scope a query to only include reports with specific comment IDs.
     */
    protected function scopeWithCommentIds(EloquentBuilder $builder, array $commentIds): EloquentBuilder
    {
        return $builder->whereIn('user_comment_id', $commentIds);
    }

    /**
     * Scope a query to only include reports with specific report IDs.
     */
    protected function scopeWithReportIds(EloquentBuilder $builder, array $reportIds): EloquentBuilder
    {
        return $builder->whereIn('user_comment_report_id', $reportIds);
    }
}
