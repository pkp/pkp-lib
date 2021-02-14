<?php

/**
 * @file classes/oai/ops/ServerOAI.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ServerOAI
 * @ingroup oai
 * @see OAIDAO
 *
 * @brief ops-specific OAI interface.
 * Designed to support both a site-wide and server-specific OAI interface
 * (based on where the request is directed).
 */

import('lib.pkp.classes.oai.OAI');
import('classes.oai.ops.OAIDAO');

class ServerOAI extends OAI {
	/** @var Site associated site object */
	var $site;

	/** @var Server associated server object */
	var $server;

	/** @var int|null Server ID; null if no server */
	var $serverId;

	/** @var OAIDAO DAO for retrieving OAI records/tokens from database */
	var $dao;


	/**
	 * @copydoc OAI::OAI()
	 */
	function __construct($config) {
		parent::__construct($config);

		$request = Application::get()->getRequest();
		$this->site = $request->getSite();
		$this->server = $request->getServer();
		$this->serverId = isset($this->server) ? $this->server->getId() : null;
		$this->dao = DAORegistry::getDAO('OAIDAO');
		$this->dao->setOAI($this);
	}

	/**
	 * Return a list of ignorable GET parameters.
	 * @return array
	 */
	function getNonPathInfoParams() {
		return array('server', 'page');
	}

	/**
	 * Convert article ID to OAI identifier.
	 * @param $articleId int
	 * @return string
	 */
	function articleIdToIdentifier($articleId) {
		return 'oai:' . $this->config->repositoryId . ':' . 'preprint/' . $articleId;
	}

	/**
	 * Convert OAI identifier to article ID.
	 * @param $identifier string
	 * @return int
	 */
	function identifierToArticleId($identifier) {
		$prefix = 'oai:' . $this->config->repositoryId . ':' . 'preprint/';
		if (strstr($identifier, $prefix)) {
			return (int) str_replace($prefix, '', $identifier);
		} else {
			return false;
		}
	}

	/**
	 * Get the server ID and section ID corresponding to a set specifier.
	 * @return int
	 */
	function setSpecToSectionId($setSpec, $serverId = null) {
		$tmpArray = preg_split('/:/', $setSpec);
		if (count($tmpArray) == 1) {
			list($serverSpec) = $tmpArray;
			$serverSpec = urldecode($serverSpec);
			$sectionSpec = null;
		} else if (count($tmpArray) == 2) {
			list($serverSpec, $sectionSpec) = $tmpArray;
			$serverSpec = urldecode($serverSpec);
			$sectionSpec = urldecode($sectionSpec);
		} else {
			return array(0, 0);
		}
		return $this->dao->getSetServerSectionId($serverSpec, $sectionSpec, $this->serverId);
	}


	//
	// OAI interface functions
	//

	/**
	 * @copydoc OAI::repositoryInfo()
	 */
	function repositoryInfo() {
		$info = new OAIRepository();

		if (isset($this->server)) {
			$info->repositoryName = $this->server->getLocalizedName();
			$info->adminEmail = $this->server->getData('contactEmail');

		} else {
			$info->repositoryName = $this->site->getLocalizedTitle();
			$info->adminEmail = $this->site->getLocalizedContactEmail();
		}

		$info->sampleIdentifier = $this->articleIdToIdentifier(1);
		$info->earliestDatestamp = $this->dao->getEarliestDatestamp(array($this->serverId));

		$info->toolkitTitle = 'Open Preprint Systems';
		$versionDao = DAORegistry::getDAO('VersionDAO');
		$currentVersion = $versionDao->getCurrentVersion();
		$info->toolkitVersion = $currentVersion->getVersionString();
		$info->toolkitURL = 'http://pkp.sfu.ca/ops/';

		return $info;
	}

	/**
	 * @copydoc OAI::validIdentifier()
	 */
	function validIdentifier($identifier) {
		return $this->identifierToArticleId($identifier) !== false;
	}

	/**
	 * @copydoc OAI::identifierExists()
	 */
	function identifierExists($identifier) {
		$recordExists = false;
		$articleId = $this->identifierToArticleId($identifier);
		if ($articleId) {
			$recordExists = $this->dao->recordExists($articleId, array($this->serverId));
		}
		return $recordExists;
	}

	/**
	 * @copydoc OAI::record()
	 */
	function record($identifier) {
		$articleId = $this->identifierToArticleId($identifier);
		if ($articleId) {
			$record = $this->dao->getRecord($articleId, array($this->serverId));
		}
		if (!isset($record)) {
			$record = false;
		}
		return $record;
	}

	/**
	 * @copydoc OAI::records()
	 */
	function records($metadataPrefix, $from, $until, $set, $offset, $limit, &$total) {
		$records = null;
		if (!HookRegistry::call('ServerOAI::records', array($this, $from, $until, $set, $offset, $limit, &$total, &$records))) {
			$sectionId = null;
			if (isset($set)) {
				list($serverId, $sectionId) = $this->setSpecToSectionId($set);
			} else {
				$serverId = $this->serverId;
			}
			$records = $this->dao->getRecords(array($serverId, $sectionId), $from, $until, $set, $offset, $limit, $total);
		}
		return $records;
	}

	/**
	 * @copydoc OAI::identifiers()
	 */
	function identifiers($metadataPrefix, $from, $until, $set, $offset, $limit, &$total) {
		$records = null;
		if (!HookRegistry::call('ServerOAI::identifiers', array($this, $from, $until, $set, $offset, $limit, &$total, &$records))) {
			$sectionId = null;
			if (isset($set)) {
				list($serverId, $sectionId) = $this->setSpecToSectionId($set);
			} else {
				$serverId = $this->serverId;
			}
			$records = $this->dao->getIdentifiers(array($serverId, $sectionId), $from, $until, $set, $offset, $limit, $total);
		}
		return $records;
	}

	/**
	 * @copydoc OAI::sets()
	 */
	function sets($offset, $limit, &$total) {
		$sets = null;
		if (!HookRegistry::call('ServerOAI::sets', array($this, $offset, $limit, &$total, &$sets))) {
			$sets = $this->dao->getServerSets($this->serverId, $offset, $limit, $total);
		}
		return $sets;
	}

	/**
	 * @copydoc OAI::resumptionToken()
	 */
	function resumptionToken($tokenId) {
		$this->dao->clearTokens();
		$token = $this->dao->getToken($tokenId);
		if (!isset($token)) {
			$token = false;
		}
		return $token;
	}

	/**
	 * @copydoc OAI::saveResumptionToken()
	 */
	function saveResumptionToken($offset, $params) {
		$token = new OAIResumptionToken(null, $offset, $params, time() + $this->config->tokenLifetime);
		$this->dao->insertToken($token);
		return $token;
	}
}


