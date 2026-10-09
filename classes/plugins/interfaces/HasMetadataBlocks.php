<?php

/**
 * @file classes/plugins/interfaces/HasMetadataBlocks.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasMetadataBlocks
 *
 * @brief Provides an interface for plugins which register custom metadata blocks
 */

namespace PKP\plugins\interfaces;

use PKP\view\MetadataBlocksRegistry;

interface HasMetadataBlocks
{
    /**
     * Register metadata blocks
     *
     * Example:
     *
     * $blocks->register(new MetadataBlock(...))
     */
    public function registerMetadataBlocks(MetadataBlocksRegistry $blocks): void;
}
