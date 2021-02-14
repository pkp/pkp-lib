<?php

/**
 * @file classes/preprint/PreprintTombstoneManager.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class PreprintTombstoneManager
 * @ingroup preprint
 *
 * @brief Class defining basic operations for preprint tombstones.
 */


class PreprintTombstoneManager {
	/**
	 * Constructor
	 */
	function __construct() {
	}

	function insertPreprintTombstone(&$preprint, &$server) {
		$sectionDao = DAORegistry::getDAO('SectionDAO'); /* @var $sectionDao SectionDAO */
		$tombstoneDao = DAORegistry::getDAO('DataObjectTombstoneDAO'); /* @var $tombstoneDao DataObjectTombstoneDAO */
		// delete preprint tombstone -- to ensure that there aren't more than one tombstone for this preprint
		$tombstoneDao->deleteByDataObjectId($preprint->getId());
		// insert preprint tombstone
		$section = $sectionDao->getById($preprint->getSectionId());
		$setSpec = urlencode($server->getPath()) . ':' . urlencode($section->getLocalizedAbbrev());
		$oaiIdentifier = 'oai:' . Config::getVar('oai', 'repository_id') . ':' . 'preprint/' . $preprint->getId();
		$OAISetObjectsIds = array(
			ASSOC_TYPE_SERVER => $server->getId(),
			ASSOC_TYPE_SECTION => $section->getId(),
		);

		$preprintTombstone = $tombstoneDao->newDataObject();
		$preprintTombstone->setDataObjectId($preprint->getId());
		$preprintTombstone->stampDateDeleted();
		$preprintTombstone->setSetSpec($setSpec);
		$preprintTombstone->setSetName($section->getLocalizedTitle());
		$preprintTombstone->setOAIIdentifier($oaiIdentifier);
		$preprintTombstone->setOAISetObjectsIds($OAISetObjectsIds);
		$tombstoneDao->insertObject($preprintTombstone);

		if (HookRegistry::call('PreprintTombstoneManager::insertPreprintTombstone', array(&$preprintTombstone, &$preprint, &$server))) return;
	}
}


