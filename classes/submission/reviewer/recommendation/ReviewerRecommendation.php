<?php

namespace PKP\submission\reviewer\recommendation;

use APP\facades\Repo;
use APP\core\Application;
use Exception;
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
    // TODO : add `recommendation_id` as guarded column once pkp/pkp-lib#10292 and pkp/pkp-lib#10562 merged
    protected $guarded = [];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'value'         => 'integer',
            'context_id'    => 'integer',
            'status'        => 'integer', // We cast the boolean to corresponding int e.g. true/false to 1/0
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
     * Get default recommendation seed data
     */
    public static function seedableRecommendations(): array
    {
        return [
            1 => 'reviewer.article.decision.accept', // SUBMISSION_REVIEWER_RECOMMENDATION_ACCEPT
            2 => 'reviewer.article.decision.pendingRevisions', // SUBMISSION_REVIEWER_RECOMMENDATION_PENDING_REVISIONS
            3 => 'reviewer.article.decision.resubmitHere', // SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_HERE
            4 => 'reviewer.article.decision.resubmitElsewhere', // SUBMISSION_REVIEWER_RECOMMENDATION_RESUBMIT_ELSEWHERE
            5 => 'reviewer.article.decision.decline', // SUBMISSION_REVIEWER_RECOMMENDATION_DECLINE
            6 => 'reviewer.article.decision.seeComments', // SUBMISSION_REVIEWER_RECOMMENDATION_SEE_COMMENTS
        ];
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

    protected function value(): Attribute
    {
        return Attribute::make(
            set: function (?int $value) {
                if ($this->getRawOriginal('value')) {
                    return $this->getRawOriginal('value');
                }

                if ($value) {
                    $existingRecommendation = static::query()
                        ->withContextId($this->contextId)
                        ->where('value', $value)
                        ->exists();
                    
                    if ($existingRecommendation) {
                        throw new Exception(
                            "Given recommendation value : {$value} already exist for given context"
                        );
                    }

                    return $value;
                }

                $lastRecommendationValue = static::query()
                    ->withContextId($this->contextId)
                    ->when(
                        $this->id,
                        fn ($query) => $query->where($this->getKeyName(), '!=', $this->id)
                    )
                    ->orderBy($this->getKeyName(), 'desc')
                    ->first()?->value ?? 0;
                
                return $lastRecommendationValue + 1;
            }
        );
    }
    
    protected function removable(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::reviewAssignment()
                ->getCollector()
                ->filterByRecommenddations([$this->value])
                ->getQueryBuilder()
                ->exists()
        )->shouldCache();
    }

    /**
     * 
     */
    protected function context(): Attribute
    {
        return Attribute::make(
            get: fn () => Application::getContextDAO()->getById($this->contextId),
        )->shouldCache();
    }

    protected function reviewAssignments(): Attribute
    {
        return Attribute::make(
            get: fn () => Repo::reviewAssignment()
                ->getCollector()
                ->filterByRecommenddations([$this->id])
                ->getMany()
        )->shouldCache();
    }

    /**
     * 
     */
    public function scopeWithContextId(Builder $query, int $contextId): Builder
    {
        return $query->where('context_id', $contextId);
    }

    public function scopeWithActive(Builder $query, bool $active = true): Builder
    {
        return $query->where('status', $active);
    }

    public function scopeWithRecommendation(Builder $query, int $recommendation): Builder
    {
        return $query->where('value', $recommendation);
    }
}
