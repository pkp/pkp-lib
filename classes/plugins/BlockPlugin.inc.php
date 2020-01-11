<?php

/**
 * @file classes/plugins/BlockPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BlockPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for block plugins
 */

import('lib.pkp.classes.plugins.LazyLoadPlugin');

abstract class BlockPlugin extends LazyLoadPlugin {

	//
	// Override public methods from Plugin
	//
	/**
	 * Determine whether or not this plugin is currently enabled.
	 *
	 * @param $contextId int Context ID (journal/press)
	 * @return boolean
	 */
	function getEnabled($contextId = null) {
		return $this->getSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'enabled');
	}

	/**
	 * Set whether or not this plugin is currently enabled.
	 *
	 * @param $enabled boolean
	 * @param $contextId int Context ID (journal/press)
	 */
	function setEnabled($enabled, $contextId = null) {
		$this->updateSetting(is_null($contextId) ? $this->getCurrentContextId() : $contextId, 'enabled', $enabled, 'bool');
	}

	/**
	 * Get the filename of the template block. (Default behavior may
	 * be overridden through some combination of this function and the
	 * getContents function.)
	 * Returning null from this function results in an empty display.
	 *
	 * @return string
	 */
	function getBlockTemplateFilename() {
		return 'block.tpl';
	}

	/**
	 * Get the HTML contents for this block.
	 *
	 * @param $templateMgr object
	 * @param $request PKPRequest (Optional for legacy plugins)
	 * @return string
	 */
	function getContents($templateMgr, $request = null) {
		$blockTemplateFilename = $this->getBlockTemplateFilename();
		if ($blockTemplateFilename === null) return '';
		return $templateMgr->fetch($this->getTemplateResource($blockTemplateFilename));
	}
}


