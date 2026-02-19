<?php

/**
 * @file classes/publication/DAO.php
 *
 * Copyright (c) 2014-2026 Simon Fraser University
 * Copyright (c) 2000-2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write publications to the database.
 */

namespace PKP\publication;

use APP\core\Application;
use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Enumerable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\controlledVocab\ControlledVocab;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\dataCitation\DataCitation;
use PKP\services\PKPSchemaService;

/**
 * @template T of Publication
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_PUBLICATION;

    /** @copydoc EntityDAO::$table */
    public $table = 'publications';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'publication_settings';

    /** @copydoc EntityDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'publication_id';

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'submission_id';
    }

    /**
     * Instantiate a new DataObject
     */
    public function newDataObject(): Publication
    {
        return app(Publication::class);
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
            ->select('p.' . $this->primaryKeyColumn)
            ->pluck('p.' . $this->primaryKeyColumn);
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
            foreach ($rows as $row) {
                yield $row->publication_id => $this->fromRow($row);
            }
        });
    }

    /**
     * Get the publication dates of the first and last publications
     * matching the passed query
     *
     * @return object self::$min_date_published, self::$max_date_published
     */
    public function getDateBoundaries(Collector $query): object
    {
        return $query
            ->getQueryBuilder()
            ->reorder()
            ->select([
                DB::raw('MIN(p.date_published) AS min_date_published, MAX(p.date_published) AS max_date_published')
            ])
            ->first();
    }

    /**
     * Is the urlPath a duplicate?
     *
     * Checks if the urlPath is used in any other submission than the one
     * passed
     *
     * A urlPath may be duplicated across more than one publication of the
     * same submission. But two publications in two different submissions
     * can not share the same urlPath.
     *
     * This is only applied within a single context.
     */
    public function isDuplicateUrlPath(string $urlPath, int $submissionId, int $contextId): bool
    {
        if (!strlen($urlPath)) {
            return false;
        }
        return (bool) DB::table('publications as p')
            ->leftJoin('submissions as s', 's.submission_id', '=', 'p.submission_id')
            ->where('url_path', '=', $urlPath)
            ->where('p.submission_id', '!=', $submissionId)
            ->where('s.context_id', '=', $contextId)
            ->count();
    }

    /**
     * @copydoc EntityDAO::fromRow()
     */
    public function fromRow(object $row): Publication
    {
        /** @var Publication $publication */
        $publication = parent::fromRow($row);

        $this->setDoiObject($publication);

        // Set the primary locale from the submission
        $locale = DB::table('submissions as s')
            ->where('s.submission_id', '=', $publication->getData('submissionId'))
            ->value('locale');
        $publication->setData('locale', $locale);

        $citations = Repo::citation()->getByPublicationId($publication->getId());
        $publication->setData('citations', $citations);
        $publication->setData('citationsRaw', new class ($publication->getId()) implements \Stringable {
            public function __construct(public int $publicationId)
            {
            }
            public function __toString()
            {
                return Repo::citation()->getRawCitationsByPublicationId($this->publicationId)->implode(PHP_EOL);
            }
        });

        $publicationVersionString = Repo::publication()->getVersionString($publication);
        $publication->setData('versionString', $publicationVersionString);

        $this->setAuthors($publication);
        $this->setCategories($publication);
        $this->setControlledVocab($publication);
        $this->setDataCitations($publication);

        return $publication;
    }

    /**
     * @copydoc EntityDAO::insert()
     */
    public function insert(Publication $publication): int
    {
        $vocabs = $this->extractControlledVocab($publication);

        $id = parent::_insert($publication);

        $this->saveControlledVocab($vocabs, $id);
        $this->saveCategories($publication);

        Repo::citation()->importCitations(
            $publication,
            $publication->getData('citationsRaw')
        );

        return $id;
    }

    /**
     * @copydoc EntityDAO::update()
     */
    public function update(Publication $publication, ?Publication $oldPublication = null)
    {
        $vocabs = $this->extractControlledVocab($publication);

        parent::_update($publication);

        $this->saveControlledVocab($vocabs, $publication->getId());
        $this->saveCategories($publication);

        if ($oldPublication) {
            Repo::citation()->importCitations(
                $publication,
                $publication->getData('citationsRaw')
            );
        }
    }

    /**
     * @copydoc EntityDAO::delete()
     */
    public function delete(Publication $publication)
    {
        parent::_delete($publication);
    }

    /**
     * @copydoc EntityDAO::deleteById()
     */
    public function deleteById(int $publicationId): int
    {
        $affectedRows = parent::deleteById($publicationId);

        $this->deleteAuthors($publicationId);
        $this->deleteCategories($publicationId);
        $this->deleteControlledVocab($publicationId);
        $this->deleteDataCitations($publicationId);
        Repo::citation()->deleteByPublicationId($publicationId);

        return $affectedRows;
    }

    /**
     * Get publication ids that have a matching setting
     */
    public function getIdsBySetting(string $settingName, $settingValue, int $contextId): Enumerable
    {
        $q = DB::table($this->table . ' as p')
            ->join($this->settingsTable . ' as ps', 'p.publication_id', '=', 'ps.publication_id')
            ->join('submissions as s', 'p.submission_id', '=', 's.submission_id')
            ->where('ps.setting_name', '=', $settingName)
            ->where('ps.setting_value', '=', $settingValue)
            ->where('s.context_id', '=', (int) $contextId);

        return $q->select('p.publication_id')
            ->pluck('p.publication_id');
    }

    /**
     * @copydoc PKPPubIdPluginDAO::pubIdExists()
     */
    public function pubIdExists(string $pubIdType, string $pubId, int $excludePubObjectId, int $contextId): bool
    {
        return DB::table('publication_settings AS ps')
            ->join('publications AS p', 'p.publication_id', '=', 'ps.publication_id')
            ->join('submissions AS s', 'p.submission_id', '=', 's.submission_id')
            ->where('ps.setting_name', '=', "pub-id::{$pubIdType}")
            ->where('ps.setting_value', '=', $pubId)
            ->where('s.submission_id', '<>', $excludePubObjectId)
            ->where('s.context_id', '=', $contextId)
            ->count() > 0;
    }

    /**
     * @copydoc PKPPubIdPluginDAO::changePubId()
     */
    public function changePubId($pubObjectId, $pubIdType, $pubId)
    {
        DB::table($this->settingsTable)
            ->updateOrInsert(
                [
                    'publication_id' => (int) $pubObjectId,
                    'locale' => '',
                    'setting_name' => 'pub-id::' . (string) $pubIdType,
                ],
                ['setting_value' => (string) $pubId]
            );
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deletePubId()
     */
    public function deletePubId(int $pubObjectId, string $pubIdType): int
    {
        return DB::table($this->settingsTable)
            ->where('publication_id', (int) $pubObjectId)
            ->where('setting_name', '=', 'pub-id::' . $pubIdType)
            ->delete();
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
     */
    public function deleteAllPubIds(int $contextId, string $pubIdType): int
    {
        return DB::table('publication_settings AS ps')
            ->join('publications AS p', 'p.publication_id', '=', 'ps.publication_id')
            ->join('submissions AS s', 's.submission_id', '=', 'p.submission_id')
            ->where('ps.setting_name', '=', "pub-id::{$pubIdType}")
            ->where('s.context_id', '=', $contextId)
            ->delete();
    }

    /**
     * Set a publication's author properties
     */
    protected function setAuthors(Publication $publication)
    {
        $publication->setData(
            'authors',
            Repo::author()
                ->getCollector()
                ->filterByPublicationIds([$publication->getId()])
                ->orderBy(\PKP\author\Collector::ORDERBY_SEQUENCE)
                ->getMany()
                ->remember()
        );
    }

    /**
     * Delete a publication's authors
     */
    protected function deleteAuthors(int $publicationId)
    {
        $authors = Repo::author()
            ->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->getMany();

        foreach ($authors as $author) {
            Repo::author()->delete($author);
        }
    }

    /**
     * Set a publication's controlled vocabulary properties
     */
    protected function setControlledVocab(Publication $publication)
    {
        $publication->setData(
            'keywords',
            Repo::controlledVocab()->getBySymbolic(
                ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD,
                Application::ASSOC_TYPE_PUBLICATION,
                $publication->getId()
            )
        );

        $publication->setData(
            'subjects',
            Repo::controlledVocab()->getBySymbolic(
                ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT,
                Application::ASSOC_TYPE_PUBLICATION,
                $publication->getId()
            )
        );

        $publication->setData(
            'disciplines',
            Repo::controlledVocab()->getBySymbolic(
                ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE,
                Application::ASSOC_TYPE_PUBLICATION,
                $publication->getId()
            )
        );

        $publication->setData(
            'supportingAgencies',
            Repo::controlledVocab()->getBySymbolic(
                ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_AGENCY,
                Application::ASSOC_TYPE_PUBLICATION,
                $publication->getId()
            )
        );
    }

    /**
     * Remove controlled vocabulary from a publication's data
     *
     * Controlled vocabulary includes keywords, subjects, and similar
     * metadata that shouldn't be saved in the publications table.
     *
     * @see self::saveControlledVocab()
     *
     * @return array Key/value of controlled vocabulary properties
     */
    protected function extractControlledVocab(Publication $publication): array
    {
        $controlledVocabKeyedArray = array_flip([
            'disciplines',
            'keywords',
            'subjects',
            'supportingAgencies',
        ]);

        $values = array_intersect_key($publication->_data, $controlledVocabKeyedArray);
        $publication->setAllData(array_diff_key($publication->_data, $controlledVocabKeyedArray));

        return $values;
    }

    /**
     * Save controlled vocabulary properties
     *
     * @see self::extractControlledVocab()
     */
    protected function saveControlledVocab(array $values, int $publicationId)
    {
        // Update controlled vocabularly for which we have props
        foreach ($values as $prop => $value) {
            match ($prop) {
                'keywords' => Repo::controlledVocab()->insertBySymbolic(ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_KEYWORD, $value, Application::ASSOC_TYPE_PUBLICATION, $publicationId),
                'subjects' => Repo::controlledVocab()->insertBySymbolic(ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_SUBJECT, $value, Application::ASSOC_TYPE_PUBLICATION, $publicationId),
                'disciplines' => Repo::controlledVocab()->insertBySymbolic(ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_DISCIPLINE, $value, Application::ASSOC_TYPE_PUBLICATION, $publicationId),
                'supportingAgencies' => Repo::controlledVocab()->insertBySymbolic(ControlledVocab::CONTROLLED_VOCAB_SUBMISSION_AGENCY, $value, Application::ASSOC_TYPE_PUBLICATION, $publicationId),
            };
        }
    }

    /**
     * Delete controlled vocab entries for a publication
     */
    protected function deleteControlledVocab(int $publicationId)
    {
        ControlledVocab::query()
            ->withAssoc(Application::ASSOC_TYPE_PUBLICATION, $publicationId)
            ->delete();
    }

    /**
     * Set a publication's category property
     */
    protected function setCategories(Publication $publication): void
    {
        $categoryIds = PublicationCategory::withPublicationId($publication->getId())->pluck('category_id')->toArray();
        $publication->setData('categoryIds', $categoryIds);
    }

    /**
     * Save the assigned categories
     */
    protected function saveCategories(Publication $publication): void
    {
        $categoryIds = (array) $publication->getData('categoryIds');
        Repo::publication()->assignCategoriesToPublication($publication->getId(), $categoryIds);
    }

    /**
     * Delete the category assignments
     */
    protected function deleteCategories(int $publicationId): void
    {
        PublicationCategory::where('publication_id', $publicationId)->delete();
    }

    /**
     * Set a publication's Data Citations
     */
    protected function setDataCitations(Publication $publication): void
    {
        $dataCitations = DataCitation::withPublicationId($publication->getId())
            ->orderBySeq()
            ->get()
            ->values()
            ->all();
        $publication->setData('dataCitations', $dataCitations);
    }

    /**
     * Delete a publication's Data Citations
     */
    protected function deleteDataCitations(int $publicationId): void
    {
        DataCitation::where('publication_id', $publicationId)->delete();
    }

    /**
     * Set the DOI object
     *
     */
    protected function setDoiObject(Publication $publication)
    {
        if (!empty($publication->getData('doiId'))) {
            $publication->setData('doiObject', Repo::doi()->get($publication->getData('doiId')));
        }
    }

    /**
     * Get setting values for the given setting name of all submission's minor versions in the given stage and a given version major
     */
    public function getMinorVersionsSettingValues(int $submissionId, string $versionStage, int $versionMajor, string $settingName): Collection
    {
        return DB::table('publication_settings AS ps')
            ->join('publications AS p', 'p.publication_id', '=', 'ps.publication_id')
            ->where('p.submission_id', '=', $submissionId)
            ->where('p.version_stage', '=', $versionStage)
            ->where('p.version_major', '=', $versionMajor)
            ->where('ps.setting_name', '=', $settingName)
            ->select('ps.setting_value')
            ->pluck('setting_value');
    }

    /**
     * Given a set of publication IDs, return which of those IDs are
     * claimed as a source publication by another publication.
     *
     * @param int[] $publicationIds
     * @return int[]
     */
    public function getClaimedSourcePublicationIds(array $publicationIds): array
    {
        return DB::table('publications')
            ->whereIn('source_publication_id', $publicationIds)
            ->pluck('source_publication_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get all submission IDs for which DOIs can be exported.
     * If the same DOI is used for all versions: the current publication needs to have a DOI and is published.
     * If different DOIs are used for different versions: a publication that have a DOI and is published needs to exist.
     */
    public function getExportableDOIsSubmissionIds(int $contextId, bool $doiVersioning): array
    {
        return DB::table('publications as p')
            ->select(['p.submission_id'])
            ->join(
                'submissions as s',
                function (JoinClause $join) use ($doiVersioning) {
                    $join
                        ->on('p.submission_id', '=', 's.submission_id')
                        ->when((!$doiVersioning), function (Builder $qb) {
                            $qb->on('s.current_publication_id', '=', 'p.publication_id');
                        });
                }
            )
            ->where('s.context_id', '=', $contextId)
            ->where('p.status', '=', Publication::STATUS_PUBLISHED)
            ->whereNotNull('p.doi_id')
            ->pluck('p.submission_id')
            ->all();
    }
}
