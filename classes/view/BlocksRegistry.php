<?php

/**
 * @file classes/view/MetadataBlockRepository.php
 *
 * Copyright (c) 2026 Simon Fraser University
 * Copyright (c) 2026 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class Repository
 *
 * @brief A repository to register and load metadata blocks.
 */

namespace PKP\view;

use Illuminate\Support\Collection;

abstract class BlocksRegistry
{
    /** @var Collection<Block> */
    protected Collection $_blocks;

    /**
     * True when the the default block
     * registration process has been completed.
     */
    protected bool $hasRegistered = false;

    public function __construct()
    {
        $this->_blocks = collect([]);
    }

    public function register(Block $block): void
    {
        $this->_blocks->put($block->id, $block);
    }

    /**
     * @param string $id The MetadataBlock::$id to remove from the registry.
     */
    public function unregister(string $id): void
    {
        $this->_blocks->forget($id);
    }

    public function get(): Collection
    {
        if (!$this->hasRegistered) {
            $this->registerAll();
        }

        return $this->_blocks;
    }

    /**
     * Register blocks from all sources
     *
     * Load default blocks along with any custom blocks from
     * plugins and themes.
     */
    abstract protected function registerAll(): void;
}