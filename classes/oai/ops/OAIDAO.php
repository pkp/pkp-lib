<?php

/**
 * @file classes/oai/ops/OAIDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIDAO
 *
 * @ingroup oai_ops
 *
 * @see OAI
 *
 * @brief DAO operations for the OPS OAI interface.
 */

namespace APP\oai\ops;

use APP\core\Application;
use APP\facades\Repo;
use APP\server\ServerDAO;
use Illuminate\Support\Facades\DB;
use PKP\db\DAORegistry;
use PKP\galley\DAO;
use PKP\oai\OAISet;
use PKP\oai\OAIUtils;
use PKP\oai\PKPOAIDAO;
use PKP\plugins\Hook;
use PKP\submission\PKPSubmission;
use PKP\tombstone\DataObjectTombstoneDAO;

class OAIDAO extends PKPOAIDAO
{
    // Helper DAOs
    /** @var ServerDAO */
    public $serverDao;
    /** @var DAO */
    public $galleyDao;

    public $serverCache;
    public $sectionCache;

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();
        $this->serverDao = DAORegistry::getDAO('ServerDAO');
        $this->galleyDao = Repo::galley()->dao;

        $this->serverCache = [];
        $this->sectionCache = [];
    }

    /**
     * Cached function to get a server
     *
     * @param int $serverId
     *
     * @return object
     */
    public function &getServer($serverId)
    {
        if (!isset($this->serverCache[$serverId])) {
            $this->serverCache[$serverId] = $this->serverDao->getById($serverId);
        }
        return $this->serverCache[$serverId];
    }

    /**
     * Cached function to get a server section
     *
     * @param int $sectionId
     *
     * @return object
     */
    public function &getSection($sectionId)
    {
        if (!isset($this->sectionCache[$sectionId])) {
            $this->sectionCache[$sectionId] = Repo::section()->get($sectionId);
        }
        return $this->sectionCache[$sectionId];
    }


    //
    // Sets
    //
    /**
     * Return hierarchy of OAI sets (servers plus server sections).
     *
     * @param int $serverId
     * @param int $offset
     * @param int $total
     *
     * @return array OAISet
     */
    public function &getServerSets($serverId, $offset, $limit, &$total)
    {
        if (isset($serverId)) {
            $servers = [$this->serverDao->getById($serverId)];
        } else {
            $servers = $this->serverDao->getAll(true);
            $servers = $servers->toArray();
        }

        // FIXME Set descriptions
        $sets = [];
        foreach ($servers as $server) {
            $title = $server->getLocalizedName();
            array_push($sets, new OAISet(self::setSpec($server), $title, ''));

            /** @var DataObjectTombstoneDAO */
            $tombstoneDao = DAORegistry::getDAO('DataObjectTombstoneDAO');
            $preprintTombstoneSets = $tombstoneDao->getSets(Application::ASSOC_TYPE_SERVER, $server->getId());

            $sections = Repo::section()
                ->getCollector()
                ->filterByContextIds([$server->getId()])
                ->getMany();
            foreach ($sections as $section) {
                $setSpec = self::setSpec($server, $section);
                if (array_key_exists($setSpec, $preprintTombstoneSets)) {
                    unset($preprintTombstoneSets[$setSpec]);
                }
                array_push($sets, new OAISet($setSpec, $section->getLocalizedTitle(), ''));
            }
            foreach ($preprintTombstoneSets as $preprintTombstoneSetSpec => $preprintTombstoneSetName) {
                array_push($sets, new OAISet($preprintTombstoneSetSpec, $preprintTombstoneSetName, ''));
            }
        }

        Hook::call('OAIDAO::getServerSets', [$this, $serverId, $offset, $limit, $total, &$sets]);

        $total = count($sets);
        $sets = array_slice($sets, $offset, $limit);

        return $sets;
    }

    /**
     * Return the server ID and section ID corresponding to a server/section pairing.
     *
     * @param string $serverSpec
     * @param string $sectionSpec
     * @param int $restrictServerId
     *
     * @return int[] (int, int)
     */
    public function getSetServerSectionId($serverSpec, $sectionSpec, $restrictServerId = null)
    {
        $server = $this->serverDao->getByPath($serverSpec);
        if (!isset($server) || (isset($restrictServerId) && $server->getId() != $restrictServerId)) {
            return [0, 0];
        }

        $serverId = $server->getId();
        $sectionId = null;

        if (isset($sectionSpec)) {
            $sectionId = 0;
            $sectionIterator = Repo::section()->getCollector()->filterByContextIds([$serverId])->getMany();
            foreach ($sectionIterator as $section) {
                if ($sectionSpec == OAIUtils::toValidSetSpec($section->getLocalizedAbbrev())) {
                    $sectionId = $section->getId();
                    break;
                }
            }
        }

        return [$serverId, $sectionId];
    }

    public static function setSpec($server, $section = null): string
    {
        // server path is already restricted to ascii alphanumeric, '-' and '_'
        return isset($section)
            ? $server->getPath() . ':' . OAIUtils::toValidSetSpec($section->getLocalizedAbbrev())
            : $server->getPath();
    }

    //
    // Protected methods.
    //
    /**
     * @see lib/pkp/classes/oai/PKPOAIDAO::setOAIData()
     */
    public function setOAIData($record, $row, $isRecord = true)
    {
        $server = $this->getServer($row['server_id']);
        $section = $this->getSection($row['section_id']);
        $preprintId = $row['submission_id'];

        /** @var ServerOAI */
        $oai = $this->oai;
        $record->identifier = $oai->preprintIdToIdentifier($preprintId);
        $record->sets = [self::setSpec($server, $section)];

        if ($isRecord) {
            $submission = Repo::submission()->get($preprintId);
            $galleys = Repo::galley()->getCollector()
                ->filterByPublicationIds([$submission->getCurrentPublication()->getId()])
                ->getMany();

            $record->setData('preprint', $submission);
            $record->setData('server', $server);
            $record->setData('section', $section);
            $record->setData('galleys', $galleys);
        }

        return $record;
    }

    /**
     * @copydoc PKPOAIDAO::_getRecordsRecordSetQuery
     *
     * @param null|mixed $submissionId
     */
    public function _getRecordsRecordSetQuery($setIds, $from, $until, $set, $submissionId = null, $orderBy = 'server_id, submission_id')
    {
        $serverId = array_shift($setIds);
        $sectionId = array_shift($setIds);

        return DB::table('submissions AS a')
            ->select([
                'a.last_modified AS last_modified',
                'a.submission_id AS submission_id',
                DB::raw('NULL AS tombstone_id'),
                DB::raw('NULL AS set_spec'),
                DB::raw('NULL AS oai_identifier'),
                'j.server_id AS server_id',
                's.section_id AS section_id',
            ])
            ->join('publications AS p', 'a.current_publication_id', '=', 'p.publication_id')
            ->join('sections AS s', 's.section_id', '=', 'p.section_id')
            ->join('servers AS j', 'j.server_id', '=', 'a.context_id')
            ->join('server_settings AS jsoai', function ($join) {
                return $join->on('jsoai.server_id', '=', 'j.server_id')
                    ->where('jsoai.setting_name', '=', 'enableOai')
                    ->where('jsoai.setting_value', '=', 1);
            })
            ->whereNotNull('p.date_published')
            ->where('j.enabled', '=', 1)
            ->where('a.status', '=', PKPSubmission::STATUS_PUBLISHED)
            ->when(isset($serverId), function ($query) use ($serverId) {
                return $query->where('j.server_id', '=', $serverId);
            })
            ->when(isset($sectionId), function ($query) use ($sectionId) {
                return $query->where('p.section_id', '=', $sectionId);
            })
            ->when($from, function ($query, $from) {
                return $query->where('a.last_modified', '>=', $this->datetimeToDB($from));
            })
            ->when($until, function ($query, $until) {
                return $query->where('a.last-modified', '<=', $this->datetimeToDB($until));
            })
            ->when($submissionId, function ($query, $submissionId) {
                return $query->where('a.submission_id', '=', $submissionId);
            })
            ->union(
                DB::table('data_object_tombstones AS dot')
                    ->select([
                        'dot.date_deleted AS last_modified',
                        'dot.data_object_id AS submission_id',
                        'dot.tombstone_id',
                        'dot.set_spec',
                        'dot.oai_identifier',
                    ])
                    ->when(isset($serverId), function ($query) use ($serverId) {
                        return $query->join('data_object_tombstone_oai_set_objects AS tsoj', function ($join) use ($serverId) {
                            return $join->on('tsoj.tombstone_id', '=', 'dot.tombstone_id')
                                ->where('tsoj.assoc_type', '=', Application::ASSOC_TYPE_SERVER)
                                ->where('tsoj.assoc_id', '=', $serverId);
                        })
                            ->addSelect(['tsoj.assoc_id AS server_id']);
                    }, function ($query) {
                        return $query->addSelect([DB::raw('NULL AS server_id')]);
                    })
                    ->when(isset($sectionId), function ($query) use ($sectionId) {
                        return $query->join('data_object_tombstone_oai_set_objects AS tsos', function ($join) use ($sectionId) {
                            $join->on('tsos.tombstone_id', '=', 'dot.tombstone_id')
                                ->where('tsos.assoc_type', '=', Application::ASSOC_TYPE_SECTION)
                                ->where('tsos.assoc_id', '=', $sectionId);
                        })
                            ->addSelect(['tsos.assoc_id AS section_id']);
                    }, function ($query) {
                        return $query->addSelect([DB::raw('NULL AS section_id')]);
                    })
                    ->when(isset($set), function ($query) use ($set) {
                        return $query->where('dot.set_spec', '=', $set)
                            ->orWhere('dot.set_spec', 'like', $set . ':%');
                    })
                    ->when($from, function ($query, $from) {
                        return $query->where('dot.date_deleted', '>=', $from);
                    })
                    ->when($until, function ($query, $until) {
                        return $query->where('dot.date_deleted', '<=', $until);
                    })
                    ->when($submissionId, function ($query, $submissionId) {
                        return $query->where('dot.data_object_id', '=', (int) $submissionId);
                    })
            )
            ->orderBy(DB::raw($orderBy));
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\oai\ops\OAIDAO', '\OAIDAO');
}
