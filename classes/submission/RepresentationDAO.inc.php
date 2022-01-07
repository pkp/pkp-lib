<?php
/**
 * @file classes/submission/RepresentationDAO.inc.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class RepresentationDAO
 * @ingroup submission
 *
 * @see Representation
 *
 * @brief Abstract DAO for fetching/working with DB storage of Representation objects
 */

namespace PKP\submission;

abstract class RepresentationDAO extends \PKP\db\DAO
{
    /**
     * Retrieves a representation by ID.
     *
     * @param int $representationId Representation ID.
     * @param int $publicationId Optional publication ID.
     * @param int $contextId Optional context ID.
     *
     * @return DAOResultFactory
     */
    abstract public function getById($representationId, $publicationId = null, $contextId = null);

    /**
    * Retrieves an iterator of representations for a publication
    *
    * @param int $publicationId
    * @param int $contextId
    *
    * @return DAOResultFactory
    */
    abstract public function getByPublicationId($publicationId, $contextId = null);
}

if (!PKP_STRICT_MODE) {
    class_alias('\PKP\submission\RepresentationDAO', '\RepresentationDAO');
}
