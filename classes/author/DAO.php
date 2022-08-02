<?php

/**
 * @file classes/author/DAO.php
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\facades\Repo;
use PKP\services\PKPSchemaService;

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
     * Instantiate a new DataObject
     */
    public function newDataObject(): Author
    {
        return app(Author::class);
    }

    /**
     * @copydoc EntityDAO::get()
     */
    public function get(int $id): ?Author
    {
        // This is ovveriden due to the need to include submission_locale
        // to the fromRow function
        $row = DB::table('authors as a')
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->where('a.author_id', '=', $id)
            ->select(['a.*', 's.locale AS submission_locale'])
            ->first();

        return $row ? $this->fromRow($row) : null;
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
            ->select(['a.*', 's.locale AS submission_locale'])
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
    public function fromRow(object $row): Author
    {
        $author = parent::fromRow($row);

        // Set the primary locale from the submission
        $author->setData('locale', $row->submission_locale);

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
     * Get the next sequence that should be used when adding a contributor to a publication
     */
    public function getNextSeq(int $publicationId): int
    {
        $nextSeq = 0;
        $seq = DB::table('authors as a')
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->where('p.publication_id', '=', $publicationId)
            ->max('a.seq');

        if ($seq) {
            $nextSeq = $seq + 1;
        }

        return $nextSeq;
    }

    /**
     * Reset the order of contributors in a publication
     *
     * This method resets the seq property for each contributor in a publication
     * so that they are numbered sequentially without any gaps.
     *
     * eg - 1, 3, 4, 6 will become 1, 2, 3, 4
     */
    public function resetContributorsOrder(int $publicationId)
    {
        $authorIds = $this->getIds(
            Repo::author()
                ->getCollector()
                ->filterByPublicationIds([$publicationId])
                ->orderBy(Repo::author()->getCollector()::ORDERBY_SEQUENCE)
        );
        foreach ($authorIds as $seq => $authorId) {
            DB::table('authors')->where('author_id', '=', $authorId)->update(['seq' => $seq]);
        }
    }
}
