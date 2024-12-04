<?php

/**
 * @file classes/affiliation/Collector.php
 *
 * Copyright (c) 2024 Simon Fraser University
 * Copyright (c) 2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of affiliations
 */

namespace PKP\affiliation;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Affiliation
 */
class Collector implements CollectorInterface
{
    public DAO $dao;

    public ?int $count = null;

    public ?int $offset = null;

    /** @var int|null Get affiliations with author id */
    public ?int $authorId = null;

    /** @var int[]|null Get affiliations with author ids */
    public ?array $authorIds = null;

    /** Get affiliations with a name */
    public ?string $name = null;

    /** Get affiliations with a searchPhrase */
    public ?string $searchPhrase = null;

    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    /** @copydoc DAO::getCount() */
    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /** @copydoc DAO::getIds() */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    /** @copydoc DAO::getMany() */
    public function getMany(?string $submissionLocale = null): LazyCollection
    {
        return $this->dao->getMany($this, $submissionLocale);
    }

    /**
     * Filter by single author
     */
    public function filterByAuthorId(?int $authorId): self
    {
        $this->authorId = $authorId;
        return $this;
    }

    /**
     * Filter by authors
     */
    public function filterByAuthorIds(?array $authorIds): self
    {
        $this->authorIds = $authorIds;
        return $this;
    }

    /**
     * Filter by affiliation name.
     */
    public function filterByName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Filter rors by those matching a search query
     */
    public function searchPhrase(?string $phrase): self
    {
        $this->searchPhrase = $phrase;
        return $this;
    }

    /**
     * Limit the number of objects retrieved
     */
    public function limit(?int $count): self
    {
        $this->count = $count;
        return $this;
    }

    /**
     * Offset the number of objects retrieved, for example to
     * retrieve the second page of contents
     */
    public function offset(?int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /** @copydoc CollectorInterface::getQueryBuilder() */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table($this->dao->table . ' as a')
            ->select('a.*');

        if (!is_null($this->count)) {
            $qb->limit($this->count);
        }

        if (!is_null($this->offset)) {
            $qb->offset($this->offset);
        }

        if (!is_null($this->authorId)) {
            $qb->where('a.author_id', '=', $this->authorId);
        }

        if (!is_null($this->authorIds)) {
            $qb->whereIn('a.author_id', $this->authorIds);
        }

        $qb->when($this->name !== null, function (Builder $qb) {
            $qb->whereIn('a.author_affiliation_id', function (Builder $qb) {
                $qb->select('author_affiliation_id')
                    ->from($this->dao->settingsTable)
                    ->where('setting_name', '=', 'name')
                    ->where('setting_value', $this->name);
            });
        });

        // Add app-specific query statements
        Hook::call('Affiliation::Collector', [&$qb, $this]);

        return $qb;
    }
}
