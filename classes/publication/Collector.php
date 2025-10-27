<?php

/**
 * @file classes/publication/Collector.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Collector
 *
 * @brief A helper class to configure a Query Builder to get a collection of announcements
 */

namespace PKP\publication;

use APP\publication\enums\VersionStage;
use APP\publication\Publication;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\interfaces\CollectorInterface;
use PKP\plugins\Hook;

/**
 * @template T of Publication
 */
class Collector implements CollectorInterface
{
    public \APP\publication\DAO $dao;
    public ?array $contextIds;
    public ?array $submissionIds;
    public ?array $publicationIds;
    public ?array $doiIds = null;
    public ?string $versionStage = null;
    public ?int $versionMajor = null;
    public ?array $statuses = null;
    public bool $orderByVersion = false;
    public ?int $count;
    public ?int $offset;


    public function __construct(DAO $dao)
    {
        $this->dao = $dao;
    }

    public function getCount(): int
    {
        return $this->dao->getCount($this);
    }

    /**
     * @return Collection<int,int>
     */
    public function getIds(): Collection
    {
        return $this->dao->getIds($this);
    }

    /**
     * @copydoc DAO::getMany()
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(): LazyCollection
    {
        return $this->dao->getMany($this);
    }

    /**
     * Filter by contexts
     */
    public function filterByContextIds(?array $contextIds): self
    {
        $this->contextIds = $contextIds;
        return $this;
    }

    /**
     * Filter by submissions
     */
    public function filterBySubmissionIds(?array $submissionIds): self
    {
        $this->submissionIds = $submissionIds;
        return $this;
    }

    /**
     * Filter by publication Ids
     * 
     * @param ?int[] $publicationIDs Publication IDs
     */
    public function filterByPublicationIds(?array $publicationIds): self
    {
        $this->publicationIds = $publicationIds;
        return $this;
    }

    public function filterByDoiIds(?array $doiIds): self
    {
        $this->doiIds = $doiIds;
        return $this;
    }

    public function filterByVersionStage(?string $versionStage): self
    {
        $this->versionStage = $versionStage;
        return $this;
    }

    public function filterByVersionMajor(?int $versionMajor): self
    {
        $this->versionMajor = $versionMajor;
        return $this;
    }

    public function filterByStatus(?array $statuses): self
    {
        $this->statuses = $statuses;
        return $this;
    }

    public function orderByVersion(): self
    {
        $this->orderByVersion = true;
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

    /**
     * @copydoc CollectorInterface::getQueryBuilder()
     *
     * @hook Publication::Collector [[&$qb, $this]]
     */
    public function getQueryBuilder(): Builder
    {
        $qb = DB::table('publications as p')
            ->select(['p.*']);

        if (isset($this->contextIds)) {
            $qb->join('submissions as s', 'p.submission_id', '=', 's.submission_id');
            $qb->whereIn('s.context_id', $this->contextIds);
        }

        if (isset($this->submissionIds)) {
            $qb->whereIn('p.submission_id', $this->submissionIds);
        }
        if (isset($this->publicationIds)) {
            $qb->whereIn('p.publication_id', $this->publicationIds);
        }

        $qb->when($this->doiIds !== null, function (Builder $qb) {
            $qb->whereIn('p.doi_id', $this->doiIds);
        });

        $qb->when($this->versionStage !== null, function (Builder $qb) {
            $qb->where('p.version_stage', $this->versionStage);
        });

        $qb->when($this->versionMajor !== null, function (Builder $qb) {
            $qb->where('p.version_major', $this->versionMajor);
        });

        if (isset($this->statuses)) {
            $qb->whereIn('p.status', $this->statuses);
        }

        if (isset($this->count)) {
            $qb->limit($this->count);
        }
        if (isset($this->offset)) {
            $qb->offset($this->offset);
        }

        if ($this->orderByVersion) {
            $orderCase = 'CASE p.version_stage ';
            foreach (VersionStage::cases() as $case) {
                $orderCase .= 'WHEN ' . DB::getPdo()->quote($case->value) . ' THEN ' . $case->order() . ' ';
            }
            $orderCase .= 'ELSE 999 END';

            $qb->orderByRaw('p.version_stage IS NOT NULL ASC');
            $qb->orderByRaw('CASE WHEN p.version_stage IS NULL THEN p.date_published ELSE NULL END ASC');
            $qb->orderByRaw($orderCase);
            $qb->orderBy('p.version_major', 'asc');
            $qb->orderBy('p.version_minor', 'asc');
            $qb->orderBy('p.date_published', 'desc');
        } else {
            $qb->orderBy('p.publication_id', 'asc');
        }

        // Add app-specific query statements
        Hook::call('Publication::Collector', [&$qb, $this]);

        return $qb;
    }
}
