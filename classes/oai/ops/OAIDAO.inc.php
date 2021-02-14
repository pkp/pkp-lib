<?php

/**
 * @file classes/oai/ops/OAIDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class OAIDAO
 * @ingroup oai_ops
 * @see OAI
 *
 * @brief DAO operations for the OPS OAI interface.
 */

import('lib.pkp.classes.oai.PKPOAIDAO');

class OAIDAO extends PKPOAIDAO {

 	/** Helper DAOs */
 	var $serverDao;
 	var $sectionDao;
	var $articleGalleyDao;
 	var $authorDao;

 	var $serverCache;
	var $sectionCache;

 	/**
	 * Constructor.
	 */
	function __construct() {
		parent::__construct();
		$this->serverDao = DAORegistry::getDAO('ServerDAO');
		$this->sectionDao = DAORegistry::getDAO('SectionDAO');
		$this->articleGalleyDao = DAORegistry::getDAO('ArticleGalleyDAO');
		$this->authorDao = DAORegistry::getDAO('AuthorDAO');

		$this->serverCache = array();
		$this->sectionCache = array();
	}

	/**
	 * @copydoc PKPOAIDAO::getEarliestDatestampQuery()
	 */
	function getEarliestDatestampQuery() {
	}

	/**
	 * Cached function to get a server
	 * @param $serverId int
	 * @return object
	 */
	function &getServer($serverId) {
		if (!isset($this->serverCache[$serverId])) {
			$this->serverCache[$serverId] = $this->serverDao->getById($serverId);
		}
		return $this->serverCache[$serverId];
	}

	/**
	 * Cached function to get a server section
	 * @param $sectionId int
	 * @return object
	 */
	function &getSection($sectionId) {
		if (!isset($this->sectionCache[$sectionId])) {
			$this->sectionCache[$sectionId] = $this->sectionDao->getById($sectionId);
		}
		return $this->sectionCache[$sectionId];
	}


	//
	// Sets
	//
	/**
	 * Return hierarchy of OAI sets (servers plus server sections).
	 * @param $serverId int
	 * @param $offset int
	 * @param $total int
	 * @return array OAISet
	 */
	function &getServerSets($serverId, $offset, $limit, &$total) {
		if (isset($serverId)) {
			$servers = array($this->serverDao->getById($serverId));
		} else {
			$servers = $this->serverDao->getAll(true);
			$servers = $servers->toArray();
		}

		// FIXME Set descriptions
		$sets = array();
		foreach ($servers as $server) {
			$title = $server->getLocalizedName();
			$abbrev = $server->getPath();
			array_push($sets, new OAISet(urlencode($abbrev), $title, ''));

			$tombstoneDao = DAORegistry::getDAO('DataObjectTombstoneDAO');
			$articleTombstoneSets = $tombstoneDao->getSets(ASSOC_TYPE_JOURNAL, $server->getId());

			$sections = $this->sectionDao->getByServerId($server->getId());
			foreach ($sections->toArray() as $section) {
				if (array_key_exists(urlencode($abbrev) . ':' . urlencode($section->getLocalizedAbbrev()), $articleTombstoneSets)) {
					unset($articleTombstoneSets[urlencode($abbrev) . ':' . urlencode($section->getLocalizedAbbrev())]);
				}
				array_push($sets, new OAISet(urlencode($abbrev) . ':' . urlencode($section->getLocalizedAbbrev()), $section->getLocalizedTitle(), ''));
			}
			foreach ($articleTombstoneSets as $articleTombstoneSetSpec => $articleTombstoneSetName) {
				array_push($sets, new OAISet($articleTombstoneSetSpec, $articleTombstoneSetName, ''));
			}
		}

		HookRegistry::call('OAIDAO::getServerSets', array($this, $serverId, $offset, $limit, $total, &$sets));

		$total = count($sets);
		$sets = array_slice($sets, $offset, $limit);

		return $sets;
	}

	/**
	 * Return the server ID and section ID corresponding to a server/section pairing.
	 * @param $serverSpec string
	 * @param $sectionSpec string
	 * @param $restrictServerId int
	 * @return array (int, int)
	 */
	function getSetServerSectionId($serverSpec, $sectionSpec, $restrictServerId = null) {
		$server =& $this->serverDao->getByPath($serverSpec);
		if (!isset($server) || (isset($restrictServerId) && $server->getId() != $restrictServerId)) {
			return array(0, 0);
		}

		$serverId = $server->getId();
		$sectionId = null;

		if (isset($sectionSpec)) {
			$section = $this->sectionDao->getByAbbrev($sectionSpec, $server->getId());
			if (isset($section)) {
				$sectionId = $section->getId();
			} else {
				$sectionId = 0;
			}
		}

		return array($serverId, $sectionId);
	}

	//
	// Protected methods.
	//
	/**
	 * @see lib/pkp/classes/oai/PKPOAIDAO::setOAIData()
	 */
	function setOAIData($record, $row, $isRecord = true) {
		$server = $this->getServer($row['server_id']);
		$section = $this->getSection($row['section_id']);
		$articleId = $row['submission_id'];

		$record->identifier = $this->oai->articleIdToIdentifier($articleId);
		$record->sets = array(urlencode($server->getPath()) . ':' . urlencode($section->getLocalizedAbbrev()));

		if ($isRecord) {
			$submission = Services::get('submission')->get($articleId);
			$galleys = $this->articleGalleyDao->getByPublicationId($submission->getCurrentPublication()->getId())->toArray();

			$record->setData('article', $submission);
			$record->setData('server', $server);
			$record->setData('section', $section);
			$record->setData('galleys', $galleys);
		}

		return $record;
	}

	/**
	 * Get a OAI records record set.
	 * @param $setIds array Objects ids that specify an OAI set,
	 * in hierarchical order.
	 * @param $from int/string *nix timestamp or ISO datetime string
	 * @param $until int/string *nix timestamp or ISO datetime string
	 * @param $set string
	 * @param $submissionId int optional
	 * @param $orderBy string UNFILTERED
	 * @return Iterable
	 */
	function _getRecordsRecordSet($setIds, $from, $until, $set, $submissionId = null, $orderBy = 'server_id, submission_id') {
		$serverId = array_shift($setIds);
		$sectionId = array_shift($setIds);

		$params = array('enableOai', (int) STATUS_PUBLISHED);
		if (isset($serverId)) $params[] = (int) $serverId;
		if (isset($sectionId)) $params[] = (int) $sectionId;
		if ($submissionId) $params[] = (int) $submissionId;
		if (isset($serverId)) $params[] = (int) $serverId;
		if (isset($sectionId)) $params[] = (int) $sectionId;
		if (isset($set)) {
			$params[] = $set;
			$params[] = $set . ':%';
		}
		if ($submissionId) $params[] = (int) $submissionId;
		$result = $this->retrieve(
			'SELECT	a.last_modified AS last_modified,
				a.submission_id AS submission_id,
				j.server_id AS server_id,
				s.section_id AS section_id,
				NULL AS tombstone_id,
				NULL AS set_spec,
				NULL AS oai_identifier
			FROM
				submissions a
				JOIN publications p ON (a.current_publication_id = p.publication_id)
				JOIN sections s ON (s.section_id = p.section_id)
				JOIN servers j ON (j.server_id = a.context_id)
				JOIN server_settings jsoai ON (jsoai.server_id = j.server_id AND jsoai.setting_name=? AND jsoai.setting_value=\'1\')
			WHERE	p.date_published IS NOT NULL AND j.enabled = 1 AND a.status = ?
				' . (isset($serverId) ?' AND j.server_id = ?':'') . '
				' . (isset($sectionId) ?' AND p.section_id = ?':'') . '
				' . ($from?' AND a.last_modified >= ' . $this->datetimeToDB($from):'') . '
				' . ($until?' AND a.last_modified <= ' . $this->datetimeToDB($until):'') . '
				' . ($submissionId?' AND a.submission_id = ?':'') . '
			UNION
			SELECT	dot.date_deleted AS last_modified,
				dot.data_object_id AS submission_id,
				' . (isset($serverId) ? 'tsoj.assoc_id' : 'NULL') . ' AS assoc_id,' . '
				' . (isset($sectionId)? 'tsos.assoc_id' : 'NULL') . ' AS section_id,
				dot.tombstone_id,
				dot.set_spec,
				dot.oai_identifier
			FROM	data_object_tombstones dot' . '
				' . (isset($serverId) ? 'JOIN data_object_tombstone_oai_set_objects tsoj ON (tsoj.tombstone_id = dot.tombstone_id AND tsoj.assoc_type = ' . ASSOC_TYPE_JOURNAL . ' AND tsoj.assoc_id = ?)' : '') . '
				' . (isset($sectionId)? 'JOIN data_object_tombstone_oai_set_objects tsos ON (tsos.tombstone_id = dot.tombstone_id AND tsos.assoc_type = ' . ASSOC_TYPE_SECTION . ' AND tsos.assoc_id = ?)' : '') . '
			WHERE	1=1
				' . (isset($set)?' AND (dot.set_spec = ? OR dot.set_spec LIKE ?)':'') . '
				' . ($from?' AND dot.date_deleted >= ' . $this->datetimeToDB($from):'') . '
				' . ($until?' AND dot.date_deleted <= ' . $this->datetimeToDB($until):'') . '
				' . ($submissionId?' AND dot.data_object_id = ?':'') . '
			ORDER BY ' . $orderBy,
			$params
		);
		return $result;
	}
}


