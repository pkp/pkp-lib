<?php

/**
 * @file classes/userPrivateNote/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class \PKP\userPrivateNote\DAO
 *
 * @see \PKP\userPrivateNote\UserPrivateNote
 *
 * @brief Operations for retrieving and modifying UserPrivateNote objects.
 */

namespace PKP\userPrivateNote;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\services\PKPSchemaService;

/**
 * @template T of UserPrivateNote
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_USER_PRIVATE_NOTE;

    /** @copydoc EntityDAO::$table */
    public $table = 'user_private_notes';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'user_private_note_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'user_private_note_id',
        'contextId' => 'context_id',
        'userId' => 'user_id',
        'note' => 'note',
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
    public function newDataObject(): UserPrivateNote
    {
        return app(UserPrivateNote::class);
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
     *
     * @return Collection<int,int>
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('upn.' . $this->primaryKeyColumn)
            ->pluck('upn.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of publications matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->user_private_note_id => $this->fromRow($row);
            }
        });
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): UserPrivateNote
    {
        return parent::fromRow($row);
    }

    /**
     * @copydoc EntityDAO::insert()
     * @throws Exception
     */
    public function insert(UserPrivateNote $privateNote): int
    {
        return parent::_insert($privateNote);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(UserPrivateNote $privateNote): void
    {
        parent::_update($privateNote);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(UserPrivateNote $privateNote): void
    {
        parent::_delete($privateNote);
    }
}
