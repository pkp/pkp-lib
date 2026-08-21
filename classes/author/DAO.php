<?php

/**
 * @file classes/author/DAO.php
 *
 * Copyright (c) 2014-2025 Simon Fraser University
 * Copyright (c) 2000-2025 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @ingroup author
 *
 * @see \PKP\author\Author
 *
 * @brief Operations for retrieving and modifying Author objects.
 */

namespace PKP\author;

use APP\author\Author;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\facades\Repo;
use PKP\services\PKPSchemaService;

/**
 * @template T of Author
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_AUTHOR;

    /** @copydoc EntityDAO::$table */
    public $table = 'authors';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'author_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'author_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'author_id',
        'email' => 'email',
        'includeInBrowse' => 'include_in_browse',
        'publicationId' => 'publication_id',
        'seq' => 'seq',
        'contributorType' => 'contributor_type',
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'publication_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Author
    {
        return app(Author::class);
    }

    /**
     * Get an author.
     *
     * Optionally, pass the publication ID to only get an author
     * if it exists and is assigned to that publication.
     */
    public function get(int $id, ?int $publicationId = null): ?Author
    {
        // This is overridden due to the need to include submission_locale
        // to the fromRow function
        $row = DB::table('authors as a')
            ->join('publications as p', 'a.publication_id', '=', 'p.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->where('a.author_id', '=', $id)
            ->when($publicationId !== null, fn (Builder $query) => $query->where('a.publication_id', '=', $publicationId))
            ->select(['a.*', 's.locale AS submission_locale'])
            ->first();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Check if an author exists.
     *
     * Optionally, pass the publication ID to check if the author
     * exists and is assigned to that publication.
     */
    public function exists(int $id, ?int $publicationId = null): bool
    {
        return DB::table($this->table)
            ->where($this->primaryKeyColumn, '=', $id)
            ->when($publicationId !== null, fn (Builder $query) => $query->where($this->getParentColumn(), $publicationId))
            ->exists();
    }

    /**
     * Get the total count of rows matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->getCountForPagination();
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
            ->select('a.' . $this->primaryKeyColumn)
            ->pluck('a.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of publications matching the configured query
     *
     * @return LazyCollection<int,T>
     */
    public function getMany(Collector $query): LazyCollection
    {
        return LazyCollection::make(function () use ($query) {
            $rows = $query
                ->getQueryBuilder()
                ->get();
            // Batch-load settings, affiliations and roles for the whole
            // result set instead of querying per author
            $this->prefetchRelated($rows);
            try {
                foreach ($rows as $row) {
                    yield $row->author_id => $this->fromRow($row);
                }
            } finally {
                $this->clearSettingsPrefetch();
                $this->affiliationsPrefetch = null;
                $this->creditRolesPrefetch = null;
                $this->contributorRolesPrefetch = null;
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
        $author->setData('submissionLocale', $row->submission_locale);

        $author->setAffiliations(
            ($this->affiliationsPrefetch !== null && array_key_exists($author->getId(), $this->affiliationsPrefetch))
                ? $this->affiliationsPrefetch[$author->getId()]
                : Repo::affiliation()->getByAuthorId($author->getId())
        );

        $author->setCreditRoles(
            ($this->creditRolesPrefetch !== null && array_key_exists($author->getId(), $this->creditRolesPrefetch))
                ? $this->creditRolesPrefetch[$author->getId()]
                : Repo::creditContributorRole()->getCreditRolesByContributorId($author->getId())
        );

        $author->setContributorRoles(
            ($this->contributorRolesPrefetch !== null && array_key_exists($author->getId(), $this->contributorRolesPrefetch))
                ? $this->contributorRolesPrefetch[$author->getId()]
                : Repo::creditContributorRole()->getContributorRolesByContributorId($author->getId())
        );

        return $author;
    }

    /**
     * Related data prefetched for the batch of authors currently being
     * hydrated by getMany(), keyed by author id. Maps are pre-filled for
     * every id in the batch, so a missing key means "not part of this
     * batch" and fromRow() falls back to the per-author queries.
     */
    protected ?array $affiliationsPrefetch = null;
    protected ?array $creditRolesPrefetch = null;
    protected ?array $contributorRolesPrefetch = null;

    /**
     * Load settings, affiliations and credit/contributor roles for a batch
     * of author rows with one query per relation.
     */
    protected function prefetchRelated(Collection $rows): void
    {
        $this->prefetchSettings($rows);
        $ids = $rows->pluck('author_id')->all();
        if (empty($ids)) {
            return;
        }

        $this->affiliationsPrefetch = Repo::affiliation()->getByAuthorIds($ids)
            + array_fill_keys($ids, []);

        $this->creditRolesPrefetch = \PKP\author\creditContributorRole\CreditContributorRole::query()
            ->whereIn('contributor_id', $ids)
            ->withCreditRoles()
            ->select(['contributor_id', 'credit_role_identifier as role', 'credit_degree as degree'])
            ->get()
            ->groupBy('contributor_id')
            ->map(fn ($group) => $group->map(fn ($r) => ['role' => $r->role, 'degree' => $r->degree])->values()->all())
            ->all()
            + array_fill_keys($ids, []);

        $this->contributorRolesPrefetch = \PKP\author\contributorRole\ContributorRole::query()
            ->join('credit_contributor_roles as ccr', 'ccr.contributor_role_id', '=', 'contributor_roles.contributor_role_id')
            ->whereIn('ccr.contributor_id', $ids)
            ->distinct()
            ->select(['contributor_roles.*', 'ccr.contributor_id as prefetch_contributor_id'])
            ->get()
            ->groupBy('prefetch_contributor_id')
            ->map(fn ($group) => $group->all())
            ->all()
            + array_fill_keys($ids, []);
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Author $author): int
    {
        $newAuthorId = parent::_insert($author);

        $author->setId($newAuthorId);

        foreach ($author->getAffiliations() as $affiliation) {
            $affiliation->setAuthorId($newAuthorId);
            Repo::affiliation()->add($affiliation);
        }

        Repo::creditContributorRole()->addCreditRoles($author->getCreditRoles(), $newAuthorId);

        Repo::creditContributorRole()->addContributorRoles($author->getContributorRoles(), $newAuthorId);

        return $newAuthorId;
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Author $author)
    {
        Repo::affiliation()->saveAffiliations($author);

        Repo::creditContributorRole()->addCreditRoles($author->getCreditRoles(), $author->getId());

        Repo::creditContributorRole()->addContributorRoles($author->getContributorRoles(), $author->getId());

        parent::_update($author);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Author $author)
    {
        DB::table('publications')
            ->where('primary_contact_id', $author->getId())
            ->update(['primary_contact_id' => null]);

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
        $authorIds = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->orderBy(Repo::author()->getCollector()::ORDERBY_SEQUENCE)
            ->getIds();

        foreach ($authorIds as $seq => $authorId) {
            DB::table('authors')->where('author_id', '=', $authorId)->update(['seq' => $seq]);
        }
    }
}
