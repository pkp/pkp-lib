<?php
/**
 * @file classes/galley/DAO.php
 *
 * Copyright (c) 2014-2022 Simon Fraser University
 * Copyright (c) 2000-2022 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class galley
 *
 * @brief Read and write galleys to the database.
 */

namespace PKP\galley;

use APP\facades\Repo;
use APP\publication\Publication;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use PKP\core\EntityDAO;
use PKP\db\DAOResultFactory;
use PKP\identity\Identity;
use PKP\services\PKPSchemaService;
use PKP\submission\PKPSubmission;
use PKP\submission\Representation;
use PKP\submission\RepresentationDAOInterface;

class DAO extends EntityDAO implements RepresentationDAOInterface
{
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

    public function newDataObject(): Galley
    {
        return app(Galley::class);
    }

    public function get(int $id): ?Galley
    {
        return parent::get($id);
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
            ->select('g.' . $this->primaryKeyColumn)
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
            ->select('g.' . $this->primaryKeyColumn)
            ->pluck('g.' . $this->primaryKeyColumn);
    }

    /**
     * Get a collection of galleys matching the configured query
     */
    public function getMany(Collector $query): LazyCollection
    {
        $rows = $query
            ->getQueryBuilder()
            ->select(['g.*'])
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

    /** @copydoc RepresentationDAOInterface::getById() */
    public function getById(int $galleyId, ?int $publicationId = null, ?int $contextId = null): ?Galley
    {
        return $this->get($galleyId);
    }

    /** @copydoc RepresentationDAOInterface::getByPublicationId() */
    public function getByPublicationId(int $publicationId): array
    {
        return Repo::galley()->getMany(
            Repo::galley()
                ->getCollector()
                ->filterByPublicationIds([$publicationId])
        )->toArray();
    }

    /** @copydoc RepresentationDAOInterface::updateObject() */
    public function updateObject(Representation $galley): void
    {
        $this->update($galley);
    }

    /**
     * @copydoc PKPPubIdPluginDAO::pubIdExists()
     */
    public function pubIdExists($pubIdType, $pubId, $excludePubObjectId, $contextId)
    {
        $result = $this->deprecatedDao->retrieve(
            'SELECT COUNT(*) AS row_count
			FROM publication_galley_settings pgs
				INNER JOIN publication_galleys pg ON pgs.galley_id = pg.galley_id
				INNER JOIN publications p ON pg.publication_id = p.publication_id
				INNER JOIN submissions s ON p.submission_id = s.submission_id
			WHERE pgs.setting_name = ? AND pgs.setting_value = ? AND pgs.galley_id <> ? AND s.context_id = ?',
            [
                'pub-id::' . $pubIdType,
                $pubId,
                (int) $excludePubObjectId,
                (int) $contextId
            ]
        );
        $row = $result->current();
        return $row ? (bool) $row->row_count : false;
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
    public function deletePubId($pubObjectId, $pubIdType)
    {
        $settingName = 'pub-id::' . $pubIdType;
        $this->deprecatedDao->update(
            'DELETE FROM publication_galley_settings WHERE setting_name = ? AND galley_id = ?',
            [
                $settingName,
                (int)$pubObjectId
            ]
        );
    }

    /**
     * @copydoc PKPPubIdPluginDAO::deleteAllPubIds()
     */
    public function deleteAllPubIds($contextId, $pubIdType)
    {
        $settingName = 'pub-id::' . $pubIdType;

        $galleyIds = Repo::galley()->getIds(
            Repo::galley()
                ->getCollector()
                ->filterByContextIds([(int) $contextId])
        );

        foreach ($galleyIds as $galleyId) {
            $this->deprecatedDao->update(
                'DELETE FROM publication_galley_settings WHERE setting_name = ? AND galley_id = ?',
                [$settingName, $galleyId]
            );
        }
        $this->deprecatedDao->flushCache();
    }

    /**
     * Get all published submission galleys (eventually with a pubId assigned and) matching the specified settings.
     *
     * @param int $contextId optional
     * @param string $pubIdType
     * @param string $title optional
     * @param string $author optional
     * @param int $issueId optional
     * @param string $pubIdSettingName optional
     * (e.g. medra::status or medra::registeredDoi)
     * @param string $pubIdSettingValue optional
     * @param DBResultRange $rangeInfo optional
     *
     * @deprecated 3.4.0
     *
     * @return DAOResultFactory
     */
    public function getExportable($contextId, $pubIdType = null, $title = null, $author = null, $issueId = null, $pubIdSettingName = null, $pubIdSettingValue = null, $rangeInfo = null)
    {
        $params = [];
        if ($pubIdSettingName) {
            $params[] = $pubIdSettingName;
        }
        $params[] = PKPSubmission::STATUS_PUBLISHED;
        $params[] = (int) $contextId;
        if ($pubIdType) {
            $params[] = 'pub-id::' . $pubIdType;
        }
        if ($title) {
            $params[] = 'title';
            $params[] = '%' . $title . '%';
        }
        if ($author) {
            array_push($params, $authorQuery = '%' . $author . '%', $authorQuery);
        }
        if ($issueId) {
            $params[] = (int) $issueId;
        }
        import('classes.plugins.PubObjectsExportPlugin'); // Constant
        if ($pubIdSettingName && $pubIdSettingValue && $pubIdSettingValue != EXPORT_STATUS_NOT_DEPOSITED) {
            $params[] = $pubIdSettingValue;
        }

        $result = $this->deprecatedDao->retrieveRange(
            $sql = 'SELECT	g.*
			FROM	publication_galleys g
				LEFT JOIN publications p ON (p.publication_id = g.publication_id)
				LEFT JOIN publication_settings ps ON (ps.publication_id = p.publication_id)
				LEFT JOIN submissions s ON (s.submission_id = p.submission_id)
				LEFT JOIN submission_files sf ON (g.submission_file_id = sf.submission_file_id)
				' . ($pubIdType != null ? ' LEFT JOIN publication_galley_settings gs ON (g.galley_id = gs.galley_id)' : '')
                . ($title != null ? ' LEFT JOIN publication_settings pst ON (p.publication_id = pst.publication_id)' : '')
                . ($author != null ? ' LEFT JOIN authors au ON (p.publication_id = au.publication_id)
						LEFT JOIN author_settings asgs ON (asgs.author_id = au.author_id AND asgs.setting_name = \'' . Identity::IDENTITY_SETTING_GIVENNAME . '\')
						LEFT JOIN author_settings asfs ON (asfs.author_id = au.author_id AND asfs.setting_name = \'' . Identity::IDENTITY_SETTING_FAMILYNAME . '\')
					' : '')
                . ($pubIdSettingName != null ? ' LEFT JOIN publication_galley_settings gss ON (g.galley_id = gss.galley_id AND gss.setting_name = ?)' : '') . '
			WHERE
				s.status = ? AND s.context_id = ?
				' . ($pubIdType != null ? ' AND gs.setting_name = ? AND gs.setting_value IS NOT NULL' : '')
                . ($title != null ? ' AND (pst.setting_name = ? AND pst.setting_value LIKE ?)' : '')
                . ($author != null ? ' AND (asgs.setting_value LIKE ? OR asfs.setting_value LIKE ?)' : '')
                . ($issueId != null ? ' AND (ps.setting_name = \'issueId\' AND ps.setting_value = ? AND ps.locale = \'\'' : '')
                . (($pubIdSettingName != null && $pubIdSettingValue != null && $pubIdSettingValue == EXPORT_STATUS_NOT_DEPOSITED) ? ' AND gss.setting_value IS NULL' : '')
                . (($pubIdSettingName != null && $pubIdSettingValue != null && $pubIdSettingValue != EXPORT_STATUS_NOT_DEPOSITED) ? ' AND gss.setting_value = ?' : '')
                . (($pubIdSettingName != null && is_null($pubIdSettingValue)) ? ' AND (gss.setting_value IS NULL OR gss.setting_value = \'\')' : '') . '
				GROUP BY g.galley_id
				ORDER BY p.date_published DESC, p.publication_id DESC, g.galley_id DESC',
            $params,
            $rangeInfo
        );

        return new DAOResultFactory($result, $this, '_fromRow', [], $sql, $params, $rangeInfo);
    }
}
