<?php

/**
 * @file classes/query/Query.inc.php
 *
 * Copyright (c) 2016-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Query
 * @ingroup submission
 *
 * @see QueryDAO
 *
 * @brief Class for Query.
 */

namespace PKP\query;

use PKP\db\DAORegistry;
use PKP\note\Note;
use PKP\note\NoteDAO;

class Query extends \PKP\core\DataObject
{
    /**
     * Get query assoc type
     *
     * @return int ASSOC_TYPE_...
     */
    public function getAssocType()
    {
        return $this->getData('assocType');
    }

    /**
     * Set query assoc type
     *
     * @param int $assocType ASSOC_TYPE_...
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
        $notes = $this->getReplies(null, NoteDAO::NOTE_ORDER_DATE_CREATED, \PKP\db\DAO::SORT_DIRECTION_ASC, true);
        return $notes->next();
    }

    /**
     * Get all notes on a query.
     *
     * @param int $userId Optional user ID
     * @param int $sortBy Optional NoteDAO::NOTE_ORDER_...
     * @param int $sortOrder Optional SORT_DIRECTION_...
     * @param bool $isAdmin Optional user sees all
     *
     * @return DAOResultFactory
     */
    public function getReplies($userId = null, $sortBy = NoteDAO::NOTE_ORDER_ID, $sortOrder = \PKP\db\DAO::SORT_DIRECTION_ASC, $isAdmin = false)
    {
        $noteDao = DAORegistry::getDAO('NoteDAO'); /** @var NoteDAO $noteDao */
        return $noteDao->getByAssoc(ASSOC_TYPE_QUERY, $this->getId(), null, $sortBy, $sortOrder, $isAdmin);
    }
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\query\Query', '\Query');
}
