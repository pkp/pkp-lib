<?php

/**
 * @file classes/submission/reviewer/recommendation/ReviewerRecommendation.php
 *
 * Copyright (c) 2025 Simon Fraser University
 * Copyright (c) 2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ReviewerRecommendation
 *
 * @brief ReviewerRecommendation model class
 */

namespace PKP\submission\reviewer\recommendation;

use APP\core\Application;
use APP\facades\Repo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use PKP\core\traits\ModelWithSettings;

class ReviewerRecommendation extends Model
{
    use ModelWithSettings;

    public const DEFAULT_RECOMMENDATION_TRANSLATION_KEY = 'defaultTranslationKey';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'reviewer_recommendations';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'reviewer_recommendation_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'reviewer_recommendation_id',
        'reviewerRecommendationId',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'context_id' => 'integer',
            'status' => 'integer', // cast the boolean to corresponding int e.g. true/false to 1/0
            'type' => 'integer',
        ];
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettingsTable
     */
    public function getSettingsTable(): string
    {
        return 'reviewer_recommendation_settings';
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSchemaName
     */
    public static function getSchemaName(): ?string
    {
        return null;
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getSettings
     */
    public function getSettings(): array
    {
        return [
            'title',
            static::DEFAULT_RECOMMENDATION_TRANSLATION_KEY,
        ];
    }

    /**
     * @copydoc \PKP\core\traits\ModelWithSettings::getMultilingualProps
     */
    public function getMultilingualProps(): array
    {
        return [
            'title',
        ];
    }

    /**
     * Get attribute value removable for this recommendation
     */
    protected function removable(): Attribute
    {
        return Attribute::make(
            get: fn () => !Repo::reviewAssignment()
                ->getCollector()
                ->filterByContextIds([$this->contextId])
                ->filterByReviewerRecommendationIds([$this->id])
                ->getQueryBuilder()
                ->exists()
        )->shouldCache();
    }

    protected function recommendationType(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->type,
        )->shouldCache();
    }

    /**
     * Get associated context details as attribute
     */
    protected function context(): Attribute
    {
        return Attribute::make(
            get: fn () => Application::getContextDAO()->getById($this->contextId),
        )->shouldCache();
    }

    /**
     * Get associated review assignments details as attribute
     */
    protected function reviewAssignments(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::reviewAssignment()
                ->getCollector()
                ->filterByContextIds([$this->contextId])
                ->filterByReviewerRecommendationIds([$this->id])
                ->getMany()
        )->shouldCache();
    }

    /**
     * Scope a query to filter by context id.
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }

    /**
     * Scope a query to filter by recommendation active/inactive status.
     */
    public function scopeWithActive(Builder $query, RecommendationOption $active = RecommendationOption::ACTIVE): Builder
    {
        return is_null($active->criteria()) ? $query : $query->where('status', $active->criteria());
    }

    /**
     * Scope a query to filter by recommendation value
     */
    public function scopeWithRecommendations(Builder $query, array $reviewerRecommendationIds): Builder
    {
        return $query->whereIn('reviewer_recommendation_id', $reviewerRecommendationIds);
    }

    /**
     * Scope a query to filter by default recommendations
     */
    public function scopeWithDefaultRecommendationsOnly(Builder $query): Builder
    {
        return $query->whereIn(
            static::DEFAULT_RECOMMENDATION_TRANSLATION_KEY,
            Repo::reviewerRecommendation()->getDefaultRecommendations()
        );
    }

    /**
     * Scope a query to filter by custom recommendations
     */
    public function scopeWithCustomRecommendationsOnly(Builder $query): Builder
    {
        return $query->whereNotIn(
            static::DEFAULT_RECOMMENDATION_TRANSLATION_KEY,
            Repo::reviewerRecommendation()->getDefaultRecommendations()
        );
    }
}
