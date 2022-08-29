<?php

/**
 * @file classes/server/ServerDAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class ServerDAO
 * @ingroup server
 *
 * @see Server
 *
 * @brief Operations for retrieving and modifying Server objects.
 */

namespace APP\server;

use APP\facades\Repo;
use PKP\context\ContextDAO;
use PKP\metadata\MetadataTypeDescription;

class ServerDAO extends ContextDAO
{
    /** @copydoc SchemaDAO::$schemaName */
    public $schemaName = 'context';

    /** @copydoc SchemaDAO::$tableName */
    public $tableName = 'servers';

    /** @copydoc SchemaDAO::$settingsTableName */
    public $settingsTableName = 'server_settings';

    /** @copydoc SchemaDAO::$primaryKeyColumn */
    public $primaryKeyColumn = 'server_id';

    /** @var array Maps schema properties for the primary table to their column names */
    public $primaryTableColumns = [
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
    public function newDataObject()
    {
        return new Server();
    }

    /**
     * Retrieve the IDs and titles of all servers in an associative array.
     *
     * @return array
     */
    public function getTitles($enabledOnly = false)
    {
        $servers = [];
        $serverIterator = $this->getAll($enabledOnly);
        while ($server = $serverIterator->next()) {
            $servers[$server->getId()] = $server->getLocalizedName();
        }
        return $servers;
    }

    /**
     * Delete the public IDs of all publishing objects in a server.
     *
     * @param int $serverId
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     */
    public function deleteAllPubIds($serverId, $pubIdType)
    {
        Repo::galley()->dao->deleteAllPubIds($serverId, $pubIdType);
        Repo::submissionFile()->dao->deleteAllPubIds($serverId, $pubIdType);
        Repo::publication()->dao->deleteAllPubIds($serverId, $pubIdType);
    }

    /**
     * Check whether the given public ID exists for any publishing
     * object in a server.
     *
     * @param int $serverId
     * @param string $pubIdType One of the NLM pub-id-type values or
     * 'other::something' if not part of the official NLM list
     * (see <http://dtd.nlm.nih.gov/publishing/tag-library/n-4zh0.html>).
     * @param string $pubId
     * @param int $assocType The object type of an object to be excluded from
     *  the search. Identified by one of the ASSOC_TYPE_* constants.
     * @param int $assocId The id of an object to be excluded from the search.
     * @param bool $forSameType Whether only the same objects should be considered.
     *
     * @return bool
     */
    public function anyPubIdExists(
        $serverId,
        $pubIdType,
        $pubId,
        $assocType = MetadataTypeDescription::ASSOC_TYPE_ANY,
        $assocId = 0,
        $forSameType = false
    ) {
        $pubObjectDaos = [
            ASSOC_TYPE_SUBMISSION => Repo::publication()->dao,
            ASSOC_TYPE_GALLEY => Application::getRepresentationDAO(),
            ASSOC_TYPE_SUBMISSION_FILE => Repo::submissionFile()->dao,
        ];
        if ($forSameType) {
            $dao = $pubObjectDaos[$assocType];
            $excludedId = $assocId;
            if ($dao->pubIdExists($pubIdType, $pubId, $excludedId, $serverId)) {
                return true;
            }
            return false;
        }
        foreach ($pubObjectDaos as $daoAssocType => $dao) {
            if ($assocType == $daoAssocType) {
                $excludedId = $assocId;
            } else {
                $excludedId = 0;
            }
            if ($dao->pubIdExists($pubIdType, $pubId, $excludedId, $serverId)) {
                return true;
            }
        }
        return false;
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\APP\server\ServerDAO', '\ServerDAO');
}
