<?php

/**
 * @file classes/oai/ops/ServerOAI.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ServerOAI
 *
 * @ingroup oai
 *
 * @see OAIDAO
 *
 * @brief ops-specific OAI interface.
 * Designed to support both a site-wide and server-specific OAI interface
 * (based on where the request is directed).
 */

namespace APP\oai\ops;

use APP\core\Application;
use PKP\db\DAORegistry;
use PKP\oai\OAI;
use PKP\oai\OAIRepository;
use PKP\oai\OAIResumptionToken;
use PKP\plugins\Hook;
use PKP\site\VersionDAO;

class ServerOAI extends OAI
{
    /** @var Site associated site object */
    public $site;

    /** @var Server associated server object */
    public $server;

    /** @var int|null Server ID; null if no server */
    public $serverId;

    /** @var OAIDAO DAO for retrieving OAI records/tokens from database */
    public $dao;


    /**
     * @copydoc OAI::OAI()
     */
    public function __construct($config)
    {
        parent::__construct($config);

        $request = Application::get()->getRequest();
        $this->site = $request->getSite();
        $this->server = $request->getServer();
        $this->serverId = isset($this->server) ? $this->server->getId() : null;
        /** @var OAIDAO */
        $this->dao = DAORegistry::getDAO('OAIDAO');
        $this->dao->setOAI($this);
    }

    /**
     * Convert preprint ID to OAI identifier.
     *
     * @param int $preprintId
     *
     * @return string
     */
    public function preprintIdToIdentifier($preprintId)
    {
        return 'oai:' . $this->config->repositoryId . ':' . 'preprint/' . $preprintId;
    }

    /**
     * Convert OAI identifier to preprint ID.
     *
     * @param string $identifier
     *
     * @return int
     */
    public function identifierToPreprintId($identifier)
    {
        $prefix = 'oai:' . $this->config->repositoryId . ':' . 'preprint/';
        if (strstr($identifier, $prefix)) {
            return (int) str_replace($prefix, '', $identifier);
        } else {
            return false;
        }
    }

    /**
     * Get the server ID and section ID corresponding to a set specifier.
     *
     * @param null|mixed $serverId
     *
     * @return int
     */
    public function setSpecToSectionId($setSpec, $serverId = null)
    {
        $tmpArray = preg_split('/:/', $setSpec);
        if (count($tmpArray) == 1) {
            [$serverSpec] = $tmpArray;
            $sectionSpec = null;
        } elseif (count($tmpArray) == 2) {
            [$serverSpec, $sectionSpec] = $tmpArray;
        } else {
            return [0, 0];
        }
        return $this->dao->getSetServerSectionId($serverSpec, $sectionSpec, $this->serverId);
    }


    //
    // OAI interface functions
    //

    /**
     * @copydoc OAI::repositoryInfo()
     */
    public function repositoryInfo()
    {
        $info = new OAIRepository();

        if (isset($this->server)) {
            $info->repositoryName = $this->server->getLocalizedName();
            $info->adminEmail = $this->server->getData('contactEmail');
        } else {
            $info->repositoryName = $this->site->getLocalizedTitle();
            $info->adminEmail = $this->site->getLocalizedContactEmail();
        }

        $info->sampleIdentifier = $this->preprintIdToIdentifier(1);
        $info->earliestDatestamp = $this->dao->getEarliestDatestamp([$this->serverId]);

        $info->toolkitTitle = 'Open Preprint Systems';
        /** @var VersionDAO */
        $versionDao = DAORegistry::getDAO('VersionDAO');
        $currentVersion = $versionDao->getCurrentVersion();
        $info->toolkitVersion = $currentVersion->getVersionString();
        $info->toolkitURL = 'https://pkp.sfu.ca/ops/';

        return $info;
    }

    /**
     * @copydoc OAI::validIdentifier()
     */
    public function validIdentifier($identifier)
    {
        return $this->identifierToPreprintId($identifier) !== false;
    }

    /**
     * @copydoc OAI::identifierExists()
     */
    public function identifierExists($identifier)
    {
        $recordExists = false;
        $preprintId = $this->identifierToPreprintId($identifier);
        if ($preprintId) {
            $recordExists = $this->dao->recordExists($preprintId, [$this->serverId]);
        }
        return $recordExists;
    }

    /**
     * @copydoc OAI::record()
     */
    public function record($identifier)
    {
        $preprintId = $this->identifierToPreprintId($identifier);
        if ($preprintId) {
            $record = $this->dao->getRecord($preprintId, [$this->serverId]);
        }
        if (!isset($record)) {
            $record = false;
        }
        return $record;
    }

    /**
     * @copydoc OAI::records()
     */
    public function records($metadataPrefix, $from, $until, $set, $offset, $limit, &$total)
    {
        $records = null;
        if (!Hook::call('ServerOAI::records', [$this, $from, $until, $set, $offset, $limit, &$total, &$records])) {
            $sectionId = null;
            if (isset($set)) {
                [$serverId, $sectionId] = $this->setSpecToSectionId($set);
            } else {
                $serverId = $this->serverId;
            }
            $records = $this->dao->getRecords([$serverId, $sectionId], $from, $until, $set, $offset, $limit, $total);
        }
        return $records;
    }

    /**
     * @copydoc OAI::identifiers()
     */
    public function identifiers($metadataPrefix, $from, $until, $set, $offset, $limit, &$total)
    {
        $records = null;
        if (!Hook::call('ServerOAI::identifiers', [$this, $from, $until, $set, $offset, $limit, &$total, &$records])) {
            $sectionId = null;
            if (isset($set)) {
                [$serverId, $sectionId] = $this->setSpecToSectionId($set);
            } else {
                $serverId = $this->serverId;
            }
            $records = $this->dao->getIdentifiers([$serverId, $sectionId], $from, $until, $set, $offset, $limit, $total);
        }
        return $records;
    }

    /**
     * @copydoc OAI::sets()
     */
    public function sets($offset, $limit, &$total)
    {
        $sets = null;
        if (!Hook::call('ServerOAI::sets', [$this, $offset, $limit, &$total, &$sets])) {
            $sets = $this->dao->getServerSets($this->serverId, $offset, $limit, $total);
        }
        return $sets;
    }

    /**
     * @copydoc OAI::resumptionToken()
     */
    public function resumptionToken($tokenId)
    {
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
    public function saveResumptionToken($offset, $params)
    {
        $token = new OAIResumptionToken(null, $offset, $params, time() + $this->config->tokenLifetime);
        $this->dao->insertToken($token);
        return $token;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\oai\ops\ServerOAI', '\ServerOAI');
}
