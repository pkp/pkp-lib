<?php

/**
 * @file classes/author/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 * @ingroup author
 *
 * @see \PKP\author\Author
 *
 * @brief Operations for retrieving and modifying Author objects.
 */

namespace PKP\author;

use APP\author\Author;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\services\PKPSchemaService;
use stdClass;

class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_AUTHOR;

    /** @copydoc EntityDAO::$table */
    public $table = 'authors';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'author_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'author_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'author_id',
        'email' => 'email',
        'includeInBrowse' => 'include_in_browse',
        'publicationId' => 'publication_id',
        'seq' => 'seq',
        'userGroupId' => 'user_group_id',
    ];

    /**
     * Constructor
     */
    public function __construct(
        PKPSchemaService $schemaService
    ) {
        parent::__construct($schemaService);
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Author
    {
        return App::make(Author::class);
    }

    /**
     * @copydoc EntityDAO::get()
     */
    public function get(int $id): ?Author
    {
        return parent::get($id);
    }

    /**
     * Get the total count of rows matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('a.' . $this->primaryKeyColumn)
            ->pluck('a.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of publications matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(stdClass $row): Author
    {
        $author = parent::fromRow($row);

        // Set the primary locale from the submission
        if (property_exists($row, 'submission_locale')) {
            $author->setData('locale', $row->submission_locale);
        }

        return $author;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Author $author): int
    {
        return parent::_insert($author);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Author $author)
    {
        parent::_update($author);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Author $author)
    {
        parent::_delete($author);
    }

    /**
     * @copydoc EntityDAO::deleteById()
     */
    public function deleteById(int $authorId)
    {
        parent::deleteById($authorId);
    }
}
