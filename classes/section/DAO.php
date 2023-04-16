<?php

/**
 * @file classes/section/DAO.php
 *
 * Copyright (c) 2014-2023 Simon Fraser University
 * Copyright (c) 2003-2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @ingroup section
 *
 * @see Section
 *
 * @brief Operations for retrieving and modifying Section objects.
 */

namespace PKP\section;

use APP\section\Section;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;

abstract class DAO extends EntityDAO
{
    use EntityWithParent;

    /**
     * Get the parent object ID column name
     */
    abstract public function getParentColumn(): string;

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Section
    {
        return App::make(Section::class);
    }

    /**
     * Get the number of sections matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->select($this->primaryKeyColumn)
            ->count();
    }

    /**
     * Get a list of sections ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select($this->primaryKeyColumn)
            ->pluck($this->primaryKeyColumn);
    }

    /**
     * Get a collection of sections matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->get();

        return LazyCollection::make(function () use ($rows) {
            foreach ($rows as $row) {
                yield $row->{$this->primaryKeyColumn} => $this->fromRow($row);
            }
        });
    }

    public function insert(Section $section): int
    {
        return parent::_insert($section);
    }

    public function update(Section $section)
    {
        parent::_update($section);
    }

    public function delete(Section $section)
    {
        parent::_delete($section);
    }
}
