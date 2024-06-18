<?php
/**
 * @file classes/galley/DAO.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DAO
 *
 * @brief Read and write galleys to the database.
 */

namespace PKP\galley;

use APP\facades\Repo;
use APP\plugins\PubObjectsExportPlugin;
use APP\publication\Publication;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\core\traits\EntityWithParent;
use PKP\db\DAOResultFactory;
use PKP\identity\Identity;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use PKP\submission\Representation;
use PKP\submission\RepresentationDAOInterface;

/**
 * @template T of Galley
 *
 * @extends EntityDAO<T>
 */
class DAO extends EntityDAO implements RepresentationDAOInterface
{
    use EntityWithParent;

    /** @copydoc EntityDAO::$schema */
    public $schema = PKPSchemaService::SCHEMA_GALLEY;

    /** @copydoc EntityDAO::$table */
    public $table = 'publication_galleys';

    /** @copydoc EntityDAO::$settingsTable */
    public $settingsTable = 'publication_galley_settings';

    /** @copydoc EntityDAO::$primarykeyColumn */
    public $primaryKeyColumn = 'galley_id';

    /** @copydoc EntityDAO::$primaryTableColumns */
    public $primaryTableColumns = [
        'submissionFileId' => 'submission_file_id',
        'id' => 'galley_id',
        'isApproved' => 'is_approved',
        'locale' => 'locale',
        'label' => 'label',
        'publicationId' => 'publication_id',
        'seq' => 'seq',
        'urlPath' => 'url_path',
        'urlRemote' => 'remote_url',
        'doiId' => 'doi_id',
    ];

    /**
     * Get the parent object ID column name
     */
    public function getParentColumn(): string
    {
        return 'publication_id';
    }

    public function newDataObject(): Galley
    {
        return app(Galley::class);
    }

    public function getByUrlPath(string $urlPath, Publication $publication): ?Galley
    {
        $row = DB::table($this->table)
            ->where('publication_id', $publication->getId())
            ->where('url_path', $urlPath)
            ->first();
        return $row ? $this->fromRow($row) : null;
    }

    /**
     * Get the number of galleys matching the configured query
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
            ->select('g.' . $this->primaryKeyColumn)
            ->pluck('g.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of galleys matching the configured query
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
                yield $row->user_id = $this->fromRow($row);
            }
        });
    }

    public function fromRow(object $row): Galley
    {
        $galley = parent::fromRow($row);

        if (!empty($galley->getData('doiId'))) {
            $galley->setData('doiObject', Repo::doi()->get($galley->getData('doiId')));
        }

        return $galley;
    }

    public function insert(Galley $galley): int
    {
        return parent::_insert($galley);
    }

    public function update(Galley $galley)
    {
        parent::_update($galley);
    }

    public function delete(Galley $galley)
    {
        parent::_delete($galley);
    }

    public function getById(int $id, ?int $publicationId = null, ?int $contextId = null): ?Representation
    {
        $row = DB::table($this->table)
            ->where($this->primaryKeyColumn, $id)
            ->when(!is_null($publicationId), function (Builder $query) use ($publicationId) {
                $query->where('publication_id', $publicationId);
            })
            ->first();
        if (!$row) {
            return null;
        }
        return $this->fromRow($row);
    }

    /** @copydoc RepresentationDAOInterface::getByPublicationId() */
    public function getByPublicationId(int $publicationId): array
    {
        return Repo::galley()->getCollector()
            ->filterByPublicationIds([$publicationId])
            ->getMany()
            ->toArray();
    }

    /** @copydoc RepresentationDAOInterface::updateObject() */
    public function updateObject(Representation $galley): void
    {
        $this->update($galley);
    }

    /**
     * @copydoc PKPPubIdPluginDAO::pubIdExists()
     */
    public function pubIdExists(string $pubIdType, string $pubId, int $excludePubObjectId, int $contextId): bool
    {
        return DB::table('publication_galley_settings AS pgs')
            ->join('publication_galleys AS pg', 'pgs.galley_id', '=', 'pg.galley_id')
            ->join('publications AS p', 'pg.publication_id', '=', 'p.publication_id')
            ->join('submissions AS s', 'p.submission_id', '=', 's.submission_id')
            ->where('pgs.setting_name', '=', "pub-id::{$pubIdType}")
            ->where('pgs.setting_value', '=', $pubId)
            ->where('pgs.galley_id', '<>', $excludePubObjectId)
            ->where('s.context_id', '=', $contextId)
            ->count() > 0;
    }

    /**
     * @copydoc PKPPubIdPluginDAO::changePubId()
     */
    public function changePubId($pubObjectId, $pubIdType, $pubId)
    {
        DB::table('publication_galley_settings')
            ->where('setting_name', 'pub-id::' . $pubIdType)
            ->where('galley_id', (int) $pubObjectId)
            ->update(['setting_value' => (string) $pubId]);
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deletePubId()
     */
    public function deletePubId(int $pubObjectId, string $pubIdType): int
    {
        return DB::table('publication_galley_settings')
            ->where('setting_name', '=', "pub-id::{$pubIdType}")
            ->where('galley_id', '=', $pubObjectId)
            ->delete();
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
     */
    public function deleteAllPubIds(int $contextId, string $pubIdType): int
    {
        $affectedRows = 0;
        $galleyIds = Repo::galley()
            ->getCollector()
            ->filterByContextIds([$contextId])
            ->getIds();

        foreach ($galleyIds as $galleyId) {
            $affectedRows += $this->deletePubId($galleyId, $pubIdType);
        }
        return $affectedRows;
    }

    /**
     * Get all published submission galleys (eventually with a pubId assigned and) matching the specified settings.
     *
     * @param string $pubIdType
     * @param string $title optional
     * @param string $author optional
     * @param int $issueId optional
     * @param string $pubIdSettingName optional
     * (e.g. medra::status or medra::registeredDoi)
     * @param string $pubIdSettingValue optional
     * @param ?\PKP\db\DBResultRange $rangeInfo optional
     *
     * @deprecated 3.4.0
     *
     * @return DAOResultFactory<Galley>
     */
    public function getExportable(int $contextId, $pubIdType = null, $title = null, $author = null, $issueId = null, $pubIdSettingName = null, $pubIdSettingValue = null, $rangeInfo = null)
    {

        $q = DB::table('publication_galleys', 'g')
            ->leftJoin('publications AS p', 'p.publication_id', '=', 'g.publication_id')
            ->leftJoin('publication_settings AS ps', 'ps.publication_id', '=', 'p.publication_id')
            ->leftJoin('submissions AS s', 's.submission_id', '=', 'p.submission_id')
            ->leftJoin('submission_files AS sf', 'g.submission_file_id', '=', 'sf.submission_file_id')
            ->when($pubIdType != null, fn (Builder $q) => $q->leftJoin('publication_galley_settings AS gs', 'g.galley_id', '=', 'gs.galley_id'))
            ->when($title != null, fn (Builder $q) => $q->leftJoin('publication_settings AS pst', 'p.publication_id', '=', 'pst.publication_id'))
            ->when(
                $author != null,
                fn (Builder $q) => $q->leftJoin('authors AS au', 'p.publication_id', '=', 'au.publication_id')
                    ->leftJoin('author_settings AS asgs', fn (JoinClause $j) => $j->on('asgs.author_id', '=', 'au.author_id')->where('asgs.setting_name', '=', Identity::IDENTITY_SETTING_GIVENNAME))
                    ->leftJoin('author_settings AS asfs', fn (JoinClause $j) => $j->on('asfs.author_id', '=', 'au.author_id')->where('asfs.setting_name', '=', Identity::IDENTITY_SETTING_FAMILYNAME))
            )
            ->when($pubIdSettingName != null, fn (Builder $q) => $q->leftJoin('publication_galley_settings AS gss', fn (JoinClause $j) => $j->on('g.galley_id', '=', 'gss.galley_id')->where('gss.setting_name', '=', $pubIdSettingName)))
            ->where('s.status', '=', PKPSubmission::STATUS_PUBLISHED)
            ->where('s.context_id', '=', $contextId)
            ->when($pubIdType != null, fn (Builder $q) => $q->where('gs.setting_name', '=', "pub-id::{$pubIdType}")->whereNotNull('gs.setting_value'))
            ->when($title != null, fn (Builder $q) => $q->where('pst.setting_name', '=', 'title')->where('pst.setting_value', 'LIKE', "%{$title}%"))
            ->when($author != null, fn (Builder $q) => $q->whereRaw("CONCAT(COALESCE(asgs.setting_value, ''), ' ', COALESCE(asfs.setting_value, '')) LIKE ?", ["%{$author}%"]))
            ->when($issueId != null, fn (Builder $q) => $q->where('ps.setting_name', '=', 'issueId')->where('ps.setting_value', '=', $issueId)->where('ps.locale', '=', ''))
            ->when(
                $pubIdSettingName,
                fn (Builder $q) =>
            $q->when(
                $pubIdSettingValue === null,
                fn (Builder $q) => $q->whereRaw("COALESCE(gss.setting_value, '') = ''"),
                fn (Builder $q) => $q->when(
                    $pubIdSettingValue != PubObjectsExportPlugin::EXPORT_STATUS_NOT_DEPOSITED,
                    fn (Builder $q) => $q->where('gss.setting_value', '=', $pubIdSettingValue),
                    fn (Builder $q) => $q->whereNull('gss.setting_value')
                )
            )
            )
            ->groupBy('g.galley_id')
            ->orderByDesc('p.date_published')
            ->orderByDesc('p.publication_id')
            ->orderByDesc('g.galley_id')
            ->select('g.*');

        $result = $this->deprecatedDao->retrieveRange($q, [], $rangeInfo);
        return new DAOResultFactory($result, $this, 'fromRow', [], $q, [], $rangeInfo);
    }
}
