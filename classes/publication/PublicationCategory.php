<?php

/**
 * @file classes/publication/PublicationCategory.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PublicationCategory
 *
 * @brief Handles operations related to publication categories
 */

namespace PKP\publication;

use Eloquence\Behaviours\HasCamelCasing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

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
     */
    public function scopeWithPublicationIds(Builder $query, array $publicationIds): Builder
    {
        return $query->whereIn('publication_id', $publicationIds);
    }

    /**
     * Scope a query to only include records with specific categoryIds
     *
     * @param int[] $categoryIds Array of category IDs
     */
    public function scopeWithCategoryIds(Builder $query, array $categoryIds): Builder
    {
        return $query->whereIn('category_id', $categoryIds);
    }

}
