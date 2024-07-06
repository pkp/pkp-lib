<?php

/**
 * @file classes/tombstone/DataObjectTombstone.php
 *
 * Copyright (c) 2014-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class DataObjectTombstone
 *
 * @brief Base class for data object tombstones.
 */

namespace PKP\tombstone;

use PKP\core\Core;
use PKP\core\DataObject;

class DataObjectTombstone extends DataObject
{
    /**
     * get data object id
     */
    public function getDataObjectId(): int
    {
        return $this->getData('dataObjectId');
    }

    /**
     * set data object id
     */
    public function setDataObjectId(int $dataObjectId): void
    {
        $this->setData('dataObjectId', $dataObjectId);
    }

    /**
     * get date deleted
     */
    public function getDateDeleted(): string
    {
        return $this->getData('dateDeleted');
    }

    /**
     * set date deleted
     */
    public function setDateDeleted(string $dateDeleted)
    {
        $this->setData('dateDeleted', $dateDeleted);
    }

    /**
     * Stamp the date of the deletion to the current time.
     */
    public function stampDateDeleted(): void
    {
        $this->setDateDeleted(Core::getCurrentDate());
    }

    /**
     * Get oai setSpec.
     */
    public function getSetSpec(): string
    {
        return $this->getData('setSpec');
    }

    /**
     * Set oai setSpec.
     */
    public function setSetSpec(string $setSpec): void
    {
        $this->setData('setSpec', $setSpec);
    }

    /**
     * Get oai setName.
     */
    public function getSetName(): string
    {
        return $this->getData('setName');
    }

    /**
     * Set oai setName.
     */
    public function setSetName(string $setName): void
    {
        $this->setData('setName', $setName);
    }

    /**
     * Get oai identifier.
     */
    public function getOAIIdentifier(): string
    {
        return $this->getData('oaiIdentifier');
    }

    /**
     * Set oai identifier.
     */
    public function setOAIIdentifier(string $oaiIdentifier): void
    {
        $this->setData('oaiIdentifier', $oaiIdentifier);
    }

    /**
     * Get an specific object id that is part of
     * the OAI set of this tombstone.
     *
     * @return ?int The object id.
     */
    public function getOAISetObjectId(int $assocType): ?int
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
     */
    public function setOAISetObjectId(int $assocType, int $assocId): void
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
    public function getOAISetObjectsIds(): array
    {
        return $this->getData('OAISetObjectsIds');
    }

    /**
     * Set all objects ids that are part of
     * the OAI set of this tombstone.
     *
     * @param $OAISetObjectsIds assocType => assocId
     */
    public function setOAISetObjectsIds(array $OAISetObjectsIds): void
    {
        $this->setData('OAISetObjectsIds', $OAISetObjectsIds);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\tombstone\DataObjectTombstone', '\DataObjectTombstone');
}
