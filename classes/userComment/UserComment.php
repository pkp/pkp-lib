<?php

/**
 * @file classes/UserComment/UserComment.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class UserComment
 *
 * @ingroup userComment
 *
 * @brief Model class describing a user comment in the system.
 */

namespace PKP\userComment;

use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PKP\core\traits\ModelWithSettings;
use PKP\userComment\relationships\UserCommentReport;

class UserComment extends Model
{
    use ModelWithSettings;

    protected $table = 'user_comments';
    protected $primaryKey = 'user_comment_id';
    protected string $settingsTable = 'user_comment_settings';
    public $timestamps = true;
    protected $guarded = ['id', 'UserCommentId'];

    /** @inheritdoc  */
    public function casts(): array
    {
        return [
            'userCommentId' => 'int',
            'userId' => 'int',
            'contextId' => 'int',
            'publicationId' => 'int',
            'isApproved' => 'boolean',
            'createdAt' => 'datetime',
            'updatedAt' => 'datetime',
        ];
    }

    /**
     * @inheritDoc
     */
    public static function getSchemaName(): ?string
    {
        return '';
    }

    /**
     * @inheritDoc
     */
    public function getSettingsTable(): string
    {
        return $this->settingsTable;
    }

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
     * Accessor for user. Can be replaced with relationship once User is converted to an Eloquent Model.
     */
    protected function user(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::user()->get($this->userId, true),
        )->shouldCache();
    }

    /**
     * An on-to-many relationship with user_comment_report table => UserCommentReport Eloquent model.
     * To eagerly fill the reports collection, the calling code should add `UserComment::with(['reports'])`
     */
    public function reports(): HasMany
    {
        return $this->hasMany(UserCommentReport::class, 'user_comment_id', 'user_comment_id');
    }

    // Scopes

    /**
     * Scope a query to only include comments with specific publication IDs.
     */
    protected function scopeWithPublicationIds(EloquentBuilder $builder, array $publicationIds): EloquentBuilder
    {
        return $builder->whereIn('publication_id', $publicationIds);
    }

    /**
     * Scope a query to only include comments with specific user IDs.
     */
    protected function scopeWithUserIds(EloquentBuilder $builder, array $userIds): EloquentBuilder
    {
        return $builder->whereIn('user_id', $userIds);
    }

    /**
     * Scope a query to only include comments with specific IDs.
     */
    protected function scopeWithUserCommentIds(EloquentBuilder $builder, array $userCommentIds): EloquentBuilder
    {
        return $builder->whereIn('user_comment_id', $userCommentIds);
    }

    /**
     * Scope a query to include comments based on if they are reported or not.
     */
    protected function scopeWithIsReported(EloquentBuilder $builder, $isReported): EloquentBuilder
    {
        return $isReported
            ? $builder->whereHas('reports')
            : $builder->whereDoesntHave('reports');
    }

    /**
     * Scope a query to only include comments with a specific approval status.
     */
    protected function scopeWithIsApproved(EloquentBuilder $builder, bool $isApproved): EloquentBuilder
    {
        return $builder->where('is_approved', $isApproved);
    }
}
