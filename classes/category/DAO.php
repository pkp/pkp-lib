<?php
/**
 * @file classes/category/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class category
 *
 * @brief Read and write categories to the database.
 */

namespace PKP\category;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;

class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = \PKP\services\PKPSchemaService::SCHEMA_CATEGORY;

    /** @copydoc EntityDAO::$table */
    public $table = 'categories';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'category_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'category_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'category_id',
        'parentId' => 'parent_id',
        'contextId' => 'context_id',
        'sequence' => 'seq',
        'path' => 'path',
        'image' => 'image',
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'context_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Category
    {
        return app(Category::class);
    }

    /**
     * Get the number of categories matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query->getQueryBuilder()->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->pluck('c.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of categories matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->category_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Category
    {
        return parent::fromRow($row);
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Category $category): int
    {
        return parent::_insert($category);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Category $category)
    {
        parent::_update($category);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Category $category)
    {
        parent::_delete($category);
    }

    /**
     * Sequentially renumber categories in their sequence order by context ID and optionally parent category ID.
     *
     * @param int $parentCategoryId Optional parent category ID
     */
    public function resequenceCategories(int $contextId, ?int $parentCategoryId = null)
    {
        $categoryIds = DB::table('categories')
            ->where('context_id', '=', $contextId)
            ->when($parentCategoryId !== null, function ($query) use ($parentCategoryId) {
                $query->where($parentCategoryId, '=', $parentCategoryId);
            })->pluck('category_id');

        $i = 0;
        foreach ($categoryIds as $categoryId) {
            DB::table('categories')->where('category_id', '=', $categoryId)->update(['seq' => ++$i]);
        }
    }

    /**
     * Assign a publication to a category
     */
    public function insertPublicationAssignment(int $categoryId, int $publicationId)
    {
        DB::table('publication_categories')->insert([
            'category_id' => $categoryId,
            'publication_id' => $publicationId,
        ]);
    }

    /**
     * Delete the assignment of a category to a publication
     */
    public function deletePublicationAssignments(int $publicationId)
    {
        DB::table('publication_categories')->where('publication_id', '=', $publicationId)->delete();
    }
}
