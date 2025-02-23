<?php

/**
 * @file lib/pkp/classes/submission/reviewer/recommendation/ReviewerRecommendation.php
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

use APP\facades\Repo;
use APP\core\Application;
use PKP\core\traits\ModelWithSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;

class ReviewerRecommendation extends Model
{
    use ModelWithSettings;

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$table
     */
    protected $table = 'reviewer_recommendations';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Model::$primaryKey
     */
    protected $primaryKey = 'recommendation_id';

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\GuardsAttributes::$guarded
     */
    protected $guarded = [
        'recommendation_id',
        'recommendationId',
        'value',
    ];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'value'         => 'integer',
            'context_id'    => 'integer',
            'status'        => 'integer', // cast the boolean to corresponding int e.g. true/false to 1/0
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(
            fn (self $recommendation) => $recommendation->value = $recommendation->value
        );
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
     * Set the recommendation value attribute
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            set: function () {
                if ($this->getRawOriginal('value')) {
                    return $this->getRawOriginal('value');
                }

                $lastRecommendationValue = static::query()
                    ->withContextId($this->contextId)
                    ->when(
                        $this->id,
                        fn ($query) => $query->where($this->getKeyName(), '!=', $this->id)
                    )
                    ->orderBy($this->getKeyName(), 'desc')
                    ->first()?->value ?? 0;
                
                $lastDefaultRecommendationValue = last(array_keys(Repo::reviewerRecommendation()->getDefaultRecommendations()));

                return ($lastRecommendationValue >= $lastDefaultRecommendationValue 
                    ? $lastRecommendationValue 
                    : $lastDefaultRecommendationValue
                ) + 1;
            }
        );
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
                ->filterByRecommenddations([$this->value])
                ->getQueryBuilder()
                ->exists()
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
                ->filterByRecommenddations([$this->value])
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
     * Scope a query to filter by recommendation active status.
     */
    public function scopeWithActive(Builder $query, bool $active = true): Builder
    {
        return $query->where('status', $active);
    }

    /**
     * Scope a query to filter by recommendation value
     */
    public function scopeWithRecommendations(Builder $query, array $recommendations): Builder
    {
        return $query->whereIn('value', $recommendations);
    }
}
