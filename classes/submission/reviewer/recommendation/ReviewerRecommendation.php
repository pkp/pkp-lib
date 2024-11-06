<?php

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
    // TODO : add `recommendation_id` and `removable` as guarded column once pkp/pkp-lib#10292 and pkp/pkp-lib#10562 merged
    protected $guarded = [];

    /**
     * @copydoc \Illuminate\Database\Eloquent\Concerns\HasAttributes::casts
     */
    protected function casts(): array
    {
        return [
            'context_id'    => 'integer',
            'status'        => 'boolean',
            'removable'     => 'boolean',
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
    
    protected function removable(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!$this->getRawOriginal('removable')) {
                    return false;
                }

                $reviewAssignmentCount = Repo::reviewAssignment()
                    ->getCollector()
                    ->filterByRecommenddations([$this->id])
                    ->getCount();
                
                return $reviewAssignmentCount === 0;
            },
            set: function () {
                return $this->getRawOriginal('removable');
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
