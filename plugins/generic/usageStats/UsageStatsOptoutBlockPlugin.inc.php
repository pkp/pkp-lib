<?php

/**
 * @file plugins/generic/usageStats/UsageStatsOptoutBlockPlugin.inc.php
 *
 * Copyright (c) 2013-2018 Simon Fraser University
 * Copyright (c) 2003-2018 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class UsageStatsOptoutBlockPlugin
 * @ingroup plugins_generic_usageStats
 *
 * @brief Opt-out component.
 */

import('lib.pkp.classes.plugins.BlockPlugin');

class UsageStatsOptoutBlockPlugin extends BlockPlugin {
	/** @var UsageStatsPlugin Parent plugin */
	protected $_parentPlugin;

	/**
	 * Constructor
	 * @param $parentPlugin UsageStatsPlugin
	 */
	function __construct($parentPlugin) {
		$this->_parentPlugin = $parentPlugin;
		parent::__construct();
	}

	/**
	 * @copydoc PKPPlugin::getHideManagement()
	 */
	function getHideManagement() {
		return true;
	}

	/**
	 * @copydoc PKPPlugin::getName()
	 */
	function getName() {
		return 'UsageStatsOptoutBlockPlugin';
	}

	/**
	 * @copydoc PKPPlugin::getDisplayName()
	 */
	function getDisplayName() {
		return __('plugins.reports.usageStats.optout.displayName');
	}

	/**
	 * @copydoc PKPPlugin::getDescription()
	 */
	function getDescription() {
		return __('plugins.generic.usageStats.optout.description');
	}

	/**
	 * @copydoc PKPPlugin::isSitePlugin()
	 */
	function isSitePlugin() {
		return false;
	}

	/**
	 * @copydoc Plugin::getPluginPath()
	 */
	public function getPluginPath() {
		return $this->_parentPlugin->getPluginPath();
	}

	/**
	 * @copydoc BlockPlugin::getEnabled()
	 */
	function getEnabled($contextId = null) {
		return $this->_parentPlugin->getEnabled($contextId);
	}

	/**
	 * @copydoc BlockPlugin::getContents()
	 */
	function getContents($templateMgr, $request = null) {
		return $templateMgr->fetch($this->getTemplateResourceName(true) . ':templates/' . $this->getBlockTemplateFilename());
	}

	/**
	 * @copydoc BlockPlugin::getBlockContext()
	 */
	function getBlockContext() {
		// if enabled, this block has to be in the sidebar
		return BLOCK_CONTEXT_SIDEBAR;
	}

	/**
	 * copydoc BlockPlugin::getBlockTemplateFilename()
	 */
	function getBlockTemplateFilename() {
		// Return the opt-out template.
		return 'optoutBlock.tpl';
	}


}

?>
