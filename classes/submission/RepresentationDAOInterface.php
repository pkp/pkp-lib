<?php
/**
 * @file classes/galley/DAO.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2000-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class galley
 *
 * @brief An interface for representation DAOs (galleys and publication formats)
 */

namespace PKP\submission;

use PKP\plugins\PKPPubIdPluginDAO;

interface RepresentationDAOInterface extends PKPPubIdPluginDAO
{
    /**
     * Instantiate a new Representation object
     */
    public function newDataObject(): Representation;

    /**
     * Get a representation by id
     */
    public function getById(int $id, ?int $publicationId = null, ?int $contextId = null): ?Representation;

    /**
     * Get the representations of a publication
     */
    public function getByPublicationId(int $publicationId): array;

    /**
     * Update the representation object
     */
    public function updateObject(Representation $representation): void;
}
