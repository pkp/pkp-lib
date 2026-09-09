<?php

/**
 * @file classes/plugins/interfaces/HasHomepageBlocks.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class HasHomepageBlocks
 *
 * @brief Provides an interface for plugins which register custom metadata blocks
 */

namespace PKP\plugins\interfaces;

use PKP\view\HomepageBlocksRegistry;

interface HasHomepageBlocks
{
    /**
     * Register metadata blocks
     *
     * Example:
     *
     * $blocks->register(new HomepageBlock(...))
     */
    public function registerHomepageBlocks(HomepageBlocksRegistry $blocks): void;
}
