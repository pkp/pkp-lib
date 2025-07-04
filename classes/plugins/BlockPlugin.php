<?php

/**
 * @file classes/plugins/BlockPlugin.php
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * @class BlockPlugin
 *
 * @ingroup plugins
 *
 * @brief Abstract class for block plugins
 */

namespace PKP\plugins;

use PKP\core\PKPRequest;

abstract class BlockPlugin extends LazyLoadPlugin
{
    //
    // Override public methods from Plugin
    //
    /**
     * Determine whether or not this plugin is currently enabled.
     *
     * @param int $contextId Context ID (journal/press)
     *
     * @return bool
     */
    public function getEnabled($contextId = null)
    {
        return $this->getSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'enabled');
    }

    /**
     * Set whether or not this plugin is currently enabled.
     *
     * @param bool $enabled
     * @param int $contextId Context ID (journal/press)
     */
    public function setEnabled($enabled, $contextId = null)
    {
        $this->updateSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'enabled', $enabled, 'bool');
    }

    /**
     * Get the filename of the template block. (Default behavior may
     * be overridden through some combination of this function and the
     * getContents function.)
     * Returning null from this function results in an empty display.
     *
     */
    public function getBlockTemplateFilename(): string
    {
        return 'block.tpl';
    }

    /**
     * Get the HTML contents for this block.
     *
     * @param object $templateMgr
     * @param PKPRequest $request (Optional for legacy plugins)
     *
     * @return string
     */
    public function getContents($templateMgr, $request = null)
    {
        $blockTemplateFilename = $this->getBlockTemplateFilename();
        if ($blockTemplateFilename === null) {
            return '';
        }
        return $templateMgr->fetch($this->getTemplateResource($blockTemplateFilename));
    }
}
