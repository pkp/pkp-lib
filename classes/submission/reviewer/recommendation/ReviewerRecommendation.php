<?php

namespace PKP\submission\reviewer\recommendation;

use APP\facades\Repo;
use APP\core\Application;
use Exception;
use PKP\core\traits\ModelWithSettings;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use PKP\submission\reviewer\recommendation\cast\ReviewerRecommendationValueCast;

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
    // TODO : add `recommendation_id` and `removable` as guarded column once pkp/pkp-lib#10292 and pkp/pkp-lib#10562 merged
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
            'removable'     => 'boolean',
        ];
    }

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function (Model $recommendation) {
            $recommendation->value = $recommendation->value;
        });
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
                        ->first();
                    
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
            get: function () {
                if (!$this->getRawOriginal('removable')) {
                    return false;
                }

                $reviewAssignmentCount = Repo::reviewAssignment()
                    ->getCollector()
                    ->filterByRecommenddations([$this->value])
                    ->getCount();
                
                return $reviewAssignmentCount === 0;
            },
            // TODO : MUST FIX ME !!! This cause issue at data seeding in migration process
            set: function (bool $value) {
                if (!is_null($this->getRawOriginal('removable'))) {
                    return $this->getRawOriginal('removable');
                }
                return $value;
            }
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
}
