<?php

/**
 * @file classes/server/ServerDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ServerDAO
 * @ingroup server
 * @see Server
 *
 * @brief Operations for retrieving and modifying Server objects.
 */

import('lib.pkp.classes.context.ContextDAO');
import('classes.server.Server');
import('lib.pkp.classes.metadata.MetadataTypeDescription');

define('JOURNAL_FIELD_TITLE', 1);
define('JOURNAL_FIELD_SEQUENCE', 2);

class ServerDAO extends ContextDAO {
	/** @copydoc SchemaDAO::$schemaName */
	var $schemaName = 'context';

	/** @copydoc SchemaDAO::$tableName */
	var $tableName = 'servers';

	/** @copydoc SchemaDAO::$settingsTableName */
	var $settingsTableName = 'server_settings';

	/** @copydoc SchemaDAO::$primaryKeyColumn */
	var $primaryKeyColumn = 'server_id';

	/** @var array Maps schema properties for the primary table to their column names */
	var $primaryTableColumns = [
		'id' => 'server_id',
		'urlPath' => 'path',
		'enabled' => 'enabled',
		'seq' => 'seq',
		'primaryLocale' => 'primary_locale',
	];

	/**
	 * Create a new DataObject of the appropriate class
	 *
	 * @return DataObject
	 */
	public function newDataObject() {
		return new Server();
	}

	/**
	 * Retrieve the IDs and titles of all servers in an associative array.
	 * @return array
	 */
	function getTitles($enabledOnly = false) {
		$servers = array();
		$serverIterator = $this->getAll($enabledOnly);
		while ($server = $serverIterator->next()) {
			$servers[$server->getId()] = $server->getLocalizedName();
		}
		return $servers;
	}

	/**
	 * Delete the public IDs of all publishing objects in a server.
	 * @param $serverId int
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 */
	function deleteAllPubIds($serverId, $pubIdType) {
		$pubObjectDaos = ['PublicationDAO', 'ArticleGalleyDAO', 'SubmissionFileDAO'];
		foreach($pubObjectDaos as $daoName) {
			$dao = DAORegistry::getDAO($daoName);
			$dao->deleteAllPubIds($serverId, $pubIdType);
		}
	}

	/**
	 * Check whether the given public ID exists for any publishing
	 * object in a server.
	 * @param $serverId int
	 * @param $pubIdType string One of the NLM pub-id-type values or
	 * 'other::something' if not part of the official NLM list
	 * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
	 * @param $pubId string
	 * @param $assocType int The object type of an object to be excluded from
	 *  the search. Identified by one of the ASSOC_TYPE_* constants.
	 * @param $assocId int The id of an object to be excluded from the search.
	 * @param $forSameType boolean Whether only the same objects should be considered.
	 * @return boolean
	 */
	function anyPubIdExists($serverId, $pubIdType, $pubId,
			$assocType = ASSOC_TYPE_ANY, $assocId = 0, $forSameType = false) {

		$pubObjectDaos = [
			ASSOC_TYPE_SUBMISSION => DAORegistry::getDAO('SubmissionDAO'),
			ASSOC_TYPE_GALLEY => Application::getRepresentationDAO(),
			ASSOC_TYPE_SUBMISSION_FILE => DAORegistry::getDAO('SubmissionFileDAO')
		];
		if ($forSameType) {
			$dao = $pubObjectDaos[$assocType];
			$excludedId = $assocId;
			if ($dao->pubIdExists($pubIdType, $pubId, $excludedId, $serverId)) return true;
			return false;
		}
		foreach($pubObjectDaos as $daoAssocType => $dao) {
			if ($assocType == $daoAssocType) {
				$excludedId = $assocId;
			} else {
				$excludedId = 0;
			}
			if ($dao->pubIdExists($pubIdType, $pubId, $excludedId, $serverId)) return true;
		}
		return false;
	}
}
