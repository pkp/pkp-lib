<?php

/**
 * @file classes/query/Query.php
 *
 * Copyright (c) 2016-2024 Simon Fraser University
 * Copyright (c) 2003-2024 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Query
 *
 * @ingroup submission
 *
 * @see QueryDAO
 *
 * @brief Class for Query.
 */

namespace PKP\query;

use Illuminate\Support\LazyCollection;
use PKP\core\PKPApplication;
use PKP\db\DAORegistry;
use PKP\db\DAO;
use PKP\note\Note;

class Query extends \PKP\core\DataObject
{
    /**
     * Get query assoc type
     *
     * @return int Application::ASSOC_TYPE_...
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set query assoc type
     *
     * @param int $assocType Application::ASSOC_TYPE_...
     */
    public function setAssocType($assocType)
    {
        $this->setData('assocType', $assocType);
    }

    /**
     * Get query assoc ID
     *
     * @return int
     */
    public function getAssocId()
    {
        return $this->getData('assocId');
    }

    /**
     * Set query assoc ID
     *
     * @param int $assocId
     */
    public function setAssocId($assocId)
    {
        $this->setData('assocId', $assocId);
    }

    /**
     * Get stage ID
     *
     * @return int
     */
    public function getStageId()
    {
        return $this->getData('stageId');
    }

    /**
     * Set stage ID
     *
     * @param int $stageId
     */
    public function setStageId($stageId)
    {
        return $this->setData('stageId', $stageId);
    }

    /**
     * Get sequence of query.
     *
     * @return float
     */
    public function getSequence()
    {
        return $this->getData('sequence');
    }

    /**
     * Set sequence of query.
     *
     * @param float $sequence
     */
    public function setSequence($sequence)
    {
        $this->setData('sequence', $sequence);
    }

    /**
     * Get closed flag
     *
     * @return bool
     */
    public function getIsClosed()
    {
        return $this->getData('closed');
    }

    /**
     * Set closed flag
     *
     * @param bool $isClosed
     */
    public function setIsClosed($isClosed)
    {
        return $this->setData('closed', $isClosed);
    }

    /**
     * Get the "head" (first) note for this query.
     *
     * @return Note
     */
    public function getHeadNote()
    {
        return Note::withAssoc(PKPApplication::ASSOC_TYPE_QUERY, $this->getId())
            ->withSort(Note::NOTE_ORDER_DATE_CREATED, DAO::SORT_DIRECTION_ASC)
            ->first();
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\query\Query', '\Query');
}
