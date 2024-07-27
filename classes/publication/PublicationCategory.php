<?php

namespace PKP\publication;

use Illuminate\Database\Eloquent\Model;

class PublicationCategory extends Model
{
    protected $table = 'publication_categories';
    protected $primaryKey = 'publication_category_id';
    public $timestamps = false;


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
        // delete all existing entries for the publication
        self::where('publication_id', $publicationId)->delete();
    
        //  insert if the category IDs are provided
        if (!empty($categoryIds)) {
            foreach ($categoryIds as $categoryId) {
                self::create([
                    'publication_id' => $publicationId,
                    'category_id' => $categoryId
                ]);
            }
        }
    }
}
