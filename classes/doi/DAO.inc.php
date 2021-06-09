<?php

/**
 * @file classes/doi/DAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DoiDAO
 * @ingroup doi
 *
 * @see Doi
 *
 * @brief Operations for retrieving and modifying Doi objects.
 */

namespace PKP\doi;

use APP\facades\Repo;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\context\Context;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use stdClass;

class DAO extends \PKP\core\EntityDAO
{
    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_DOI;

    /** @copydoc EntityDAO::$table */
    public $table = 'dois';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'doi_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'doi_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'id' => 'doi_id',
        'contextId' => 'context_id',
        'doi' => 'doi',
        'status' => 'status'
    ];

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Doi
    {
        return App::make(Doi::class);
    }

    /**
     * @copydoc EntityDAO::get()
     */
    public function get(int $id): ?Doi
    {
        $doi = parent::get($id);
        return $doi;
    }

    /**
     * Get the number of DOIs matching the configured query
     */
    public function getCount(Collector $query): int
    {
        return $query
            ->getQueryBuilder()
            ->select('d' . $this->primaryKeyColumn)
            ->get()
            ->count();
    }

    /**
     * Get a list of ids matching the configured query
     */
    public function getIds(Collector $query): Collection
    {
        return $query
            ->getQueryBuilder()
            ->select('d.' . $this->primaryKeyColumn)
            ->pluck('d.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of DOIs matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['d.*'])
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
    public function fromRow(stdClass $row): Doi
    {
        $doi = parent::fromRow($row);
        if (empty($doi->getData('doi'))) {
            $doi->setData('resolvingUrl', '');
        } else {
            $doi->setData('resolvingUrl', $doi->getResolvingUrl());
        }

        return $doi;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Doi $doi): int
    {
        return parent::_insert($doi);
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Doi $doi)
    {
        parent::_update($doi);
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Doi $doi)
    {
        parent::_delete($doi);
    }

    /**
     * Helper to get galley IDs that are unregistered and published
     * TODO: #doi revisit with Galley EntityDAO
     *
     *
     */
    public function getUnregisteredGalleyIds(int $contextId): Collection
    {
        $q = DB::table('publications_galleys', 'g');

        // Context
        $q->whereIn('g.galley_id', function (Builder $q) use ($contextId) {
            $q->select('g.galley_id')
                ->from('publication_galleys as g')
                ->leftJoin('publications as p', 'p.publication_id', '=', 'g.publication_id')
                ->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
                ->where('s.context_id', '=', $contextId);
        });

        // Publication status
        $q->whereIn('g.galley_id', function (Builder $q) {
            $q->select('g.galley_id')
                ->from('publication_galleys as g')
                ->leftJoin('publications as p', 'p.publication_id', '=', 'g.publication_id')
                ->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
                ->where('s.status', '=', PKPSubmission::STATUS_PUBLISHED);
        });

        // DOI statuses
        $q->whereIn('g.galley_id', function (Builder $q) {
            $q->select('g.galley_id')
                ->from('publication_galleys as g')
                ->leftJoin('dois as d', 'd.doi_id', '=', 'g.doi_id')
                ->whereIn('d.status', [Doi::STATUS_UNREGISTERED, Doi::STATUS_ERROR, Doi::STATUS_STALE]);
        });

        return $q->select('g.galley_id')
            ->pluck('galley_id');
    }

    /**
     * Set DOIs as stale if they currently have an updatable status
     *
     */
    public function setDoisToStale(array $doiIds)
    {
        $q = DB::table($this->table, 'd');

        $updatableStatuses = [
            Doi::STATUS_SUBMITTED,
            Doi::STATUS_REGISTERED
        ];

        $q->whereIn('d.doi_id', $doiIds)
            ->whereIn('d.status', $updatableStatuses)
            ->update(['status' => Doi::STATUS_STALE]);
    }

    /**
     * Set DOIs as submitted once they have been added to the queue for processing
     *
     */
    public function setDoisToSubmitted(array $doiIds)
    {
        $q = DB::table($this->table, 'd');
        $q->whereIn('d.doi_id', $doiIds)
            ->update(['status' => Doi::STATUS_SUBMITTED]);
    }

    public function getAllDepositableSubmissionIds(Context $context): Collection
    {
        $enabledDoiTypes = $context->getData(Context::SETTING_ENABLED_DOI_TYPES);

        $q = DB::table($this->table, 'd')
            ->leftJoin('publications as p', 'd.doi_id', '=', 'p.doi_id')
            ->leftJoin('submissions as s', 'p.publication_id', '=', 's.current_publication_id')
            ->where('d.context_id', '=', $context->getId())
            ->where(function (Builder $q) use ($enabledDoiTypes) {
                // Publication DOIs
                $q->when(in_array(Repo::doi()::TYPE_PUBLICATION, $enabledDoiTypes), function (Builder $q) {
                    $q->whereIn('d.doi_id', function (Builder $q) {
                        $q->select('p.doi_id')
                            ->from('publications', 'p')
                            ->leftJoin('submissions as s', 'p.publication_id', '=', 's.current_publication_id')
                            ->whereColumn('p.publication_id', '=', 's.current_publication_id')
                            ->whereNotNull('p.doi_id')
                            ->where('p.status', '=', PKPSubmission::STATUS_PUBLISHED);
                    });
                })
                    // Galley DOIs
                    ->when(in_array(Repo::doi()::TYPE_REPRESENTATION, $enabledDoiTypes), function (Builder $q) {
                        $q->orWhereIn('d.doi_id', function (Builder $q) {
                            $q->select('g.doi_id')
                                ->from('publication_galleys', 'g')
                                ->leftJoin('publications as p', 'g.publication_id', '=', 'p.publication_id')
                                ->leftJoin('submissions as s', 'p.publication_id', '=', 's.current_publication_id')
                                ->whereColumn('p.publication_id', '=', 's.current_publication_id')
                                ->whereNotNull('g.doi_id')
                                ->where('p.status', '=', PKPSubmission::STATUS_PUBLISHED);
                        });
                    });
            });
        $q->whereIn('d.status', [Doi::STATUS_UNREGISTERED, Doi::STATUS_ERROR, Doi::STATUS_STALE]);
        return $q->get(['s.submission_id', 'd.doi_id']);
    }
}
