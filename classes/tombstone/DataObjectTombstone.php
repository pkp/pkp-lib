<?php

/**
 * @file classes/tombstone/DataObjectTombstone.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectTombstone
 *
 * @ingroup tombstone
 *
 * @brief Base class for data object tombstones.
 */

namespace PKP\tombstone;

use PKP\core\Core;

class DataObjectTombstone extends \PKP\core\DataObject
{
    /**
     * get data object id
     *
     * @return int
     */
    public function getDataObjectId()
    {
        return $this->getData('dataObjectId');
    }

    /**
     * set data object id
     *
     * @param int $dataObjectId
     */
    public function setDataObjectId($dataObjectId)
    {
        $this->setData('dataObjectId', $dataObjectId);
    }

    /**
     * get date deleted
     *
     * @return string
     */
    public function getDateDeleted()
    {
        return $this->getData('dateDeleted');
    }

    /**
     * set date deleted
     *
     * @param string $dateDeleted
     */
    public function setDateDeleted($dateDeleted)
    {
        $this->setData('dateDeleted', $dateDeleted);
    }

    /**
     * Stamp the date of the deletion to the current time.
     */
    public function stampDateDeleted()
    {
        return $this->setDateDeleted(Core::getCurrentDate());
    }

    /**
     * Get oai setSpec.
     *
     * @return string
     */
    public function getSetSpec()
    {
        return $this->getData('setSpec');
    }

    /**
     * Set oai setSpec.
     *
     * @param string $setSpec
     */
    public function setSetSpec($setSpec)
    {
        $this->setData('setSpec', $setSpec);
    }

    /**
     * Get oai setName.
     *
     * @return string
     */
    public function getSetName()
    {
        return $this->getData('setName');
    }

    /**
     * Set oai setName.
     *
     * @param string $setName
     */
    public function setSetName($setName)
    {
        $this->setData('setName', $setName);
    }

    /**
     * Get oai identifier.
     *
     * @return string
     */
    public function getOAIIdentifier()
    {
        return $this->getData('oaiIdentifier');
    }

    /**
     * Set oai identifier.
     *
     * @param string $oaiIdentifier
     */
    public function setOAIIdentifier($oaiIdentifier)
    {
        $this->setData('oaiIdentifier', $oaiIdentifier);
    }

    /**
     * Get an specific object id that is part of
     * the OAI set of this tombstone.
     *
     * @param int $assocType
     *
     * @return int The object id.
     */
    public function getOAISetObjectId($assocType)
    {
        $setObjectsIds = $this->getOAISetObjectsIds();
        if (isset($setObjectsIds[$assocType])) {
            return $setObjectsIds[$assocType];
        } else {
            return null;
        }
    }

    /**
     * Set an specific object id that is part of
     * the OAI set of this tombstone.
     *
     * @param int $assocType
     * @param int $assocId
     */
    public function setOAISetObjectId($assocType, $assocId)
    {
        $setObjectsIds = $this->getOAISetObjectsIds();
        $setObjectsIds[$assocType] = $assocId;

        $this->setOAISetObjectsIds($setObjectsIds);
    }

    /**
     * Get all objects ids that are part of
     * the OAI set of this tombstone.
     *
     * @return array assocType => assocId
     */
    public function getOAISetObjectsIds()
    {
        return $this->getData('OAISetObjectsIds');
    }

    /**
     * Set all objects ids that are part of
     * the OAI set of this tombstone.
     *
     * @param array $OAISetObjectsIds assocType => assocId
     */
    public function setOAISetObjectsIds($OAISetObjectsIds)
    {
        $this->setData('OAISetObjectsIds', $OAISetObjectsIds);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\tombstone\DataObjectTombstone', '\DataObjectTombstone');
}
