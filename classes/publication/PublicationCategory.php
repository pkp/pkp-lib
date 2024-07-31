<?php

namespace PKP\publication;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class PublicationCategory extends Model
{
    use HasCamelCasing;

    protected $table = 'publication_categories';
    protected $primaryKey = 'publication_category_id';
    public $timestamps = false;

    protected $fillable = [
        'publication_id', 'category_id'
    ];

    /**
     * Scope a query to only include records with a specific publicationId
     *
     */
    public function scopeWithPublicationId(Builder $query, int $publicationId): Builder
    {
        return $query->where('publication_id', $publicationId);
    }

    /**
     * Scope a query to only include records with specific categoryIds
     */
    public function scopeWithCategoryIds(Builder $query, array $categoryIds): Builder
    {
        return $query->whereIn('category_id', $categoryIds);
    }

}
