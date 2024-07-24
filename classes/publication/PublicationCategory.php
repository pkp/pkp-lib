<?php

namespace PKP\publication;

use Illuminate\Database\Eloquent\Model;

class PublicationCategory extends Model
{
    protected $table = 'publication_categories';
    protected $primaryKey = 'publication_category_id';

    protected $fillable = [
        'publication_id', 'category_id'
    ];

    /**
     * Get the categories associated with a publication.
     *
     */
    public static function getCategoriesByPublicationId(int $publicationId)
    {
        return self::where('publication_id', $publicationId)->pluck('category_id');
    }

    /**
     * Assign categories to a publication.
     *
     */
    public static function assignCategoriesToPublication(int $publicationId, array $categoryIds)
    {
        self::where('publication_id', $publicationId)->delete();

        foreach ($categoryIds as $categoryId) {
            self::create([
                'publication_id' => $publicationId,
                'category_id' => $categoryId
            ]);
        }
    }
}
