<?php

/**
 * @file classes/plugins/BlockPlugin.inc.php
 *
 * Copyright (c) 2013-2014 Simon Fraser University Library
 * Copyright (c) 2003-2014 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class BlockPlugin
 * @ingroup plugins
 *
 * @brief Abstract class for block plugins
 */

define('BLOCK_CONTEXT_LEFT_SIDEBAR',		0x00000001);
define('BLOCK_CONTEXT_RIGHT_SIDEBAR',		0x00000002);
define('BLOCK_CONTEXT_HOMEPAGE',		0x00000003);

import('lib.pkp.classes.plugins.LazyLoadPlugin');

class BlockPlugin extends LazyLoadPlugin {
	/**
	 * Constructor
	 */
	function BlockPlugin() {
		parent::LazyLoadPlugin();
	}

	/*
	 * Override public methods from PKPPlugin
	 */
	/**
	 * @see PKPPlugin::register()
	 */
	function register($category, $path) {
		$success = parent::register($category, $path);
		if ($success && $this->getEnabled()) {
			$contextMap =& $this->getContextMap();
			$blockContext = $this->getBlockContext();
			if (isset($contextMap[$blockContext])) {
				$hookName = $contextMap[$blockContext];
				HookRegistry::register($hookName, array(&$this, 'callback'));
			}
		}
		return $success;
	}

	/*
	 * Override protected methods from PKPPlugin
	 */
	/**
	 * @see PKPPlugin::getSeq()
	 *
	 * NB: In the case of block plugins, higher numbers move
	 * plugins down the page compared to other blocks.
	 */
	function getSeq() {
		return $this->getContextSpecificSetting($this->getSettingMainContext(), 'seq');
	}

	/*
	 * Block Plugin specific methods
	 */
	/**
	 * Set the sequence information for this plugin.
	 *
	 * NB: In the case of block plugins, higher numbers move
	 * plugins down the page compared to other blocks.
	 *
	 * @param $seq int
	 */
	function setSeq($seq) {
		return $this->updateContextSpecificSetting($this->getSettingMainContext(), 'seq', $seq, 'int');
	}

	/**
	 * Get the block context (e.g. BLOCK_CONTEXT_...) for this block.
	 *
	 * @return int
	 */
	function getBlockContext() {
		return $this->getContextSpecificSetting($this->getSettingMainContext(), 'context');
	}

	/**
	 * Set the block context (e.g. BLOCK_CONTEXT_...) for this block.
	 *
	 * @param $context int
	 */
	function setBlockContext($context) {
		return $this->updateContextSpecificSetting($this->getSettingMainContext(), 'context', $context, 'int');
	}


	/**
	 * Get the supported contexts (e.g. BLOCK_CONTEXT_...) for this block.
	 *
	 * @return array
	 */
	function getSupportedContexts() {
		// Will return left and right process as this is the
		// most frequent use case.
		return array(BLOCK_CONTEXT_LEFT_SIDEBAR, BLOCK_CONTEXT_RIGHT_SIDEBAR);
	}

	/**
	 * Get an associative array linking block context to hook name.
	 *
	 * @return array
	 */
	function &getContextMap() {
		static $contextMap = array(
			BLOCK_CONTEXT_LEFT_SIDEBAR => 'Templates::Common::LeftSidebar',
			BLOCK_CONTEXT_RIGHT_SIDEBAR => 'Templates::Common::RightSidebar',
		);

		$homepageHook = $this->_getContextSpecificHomepageHook();
		if ($homepageHook) $contextMap[BLOCK_CONTEXT_HOMEPAGE] = $homepageHook;

		HookRegistry::call('BlockPlugin::getContextMap', array(&$this, &$contextMap));
		return $contextMap;
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
	function getContents(&$templateMgr, $request = null) {
		$blockTemplateFilename = $this->getBlockTemplateFilename();
		if ($blockTemplateFilename === null) return '';
		return $templateMgr->fetch($this->getTemplatePath() . $blockTemplateFilename);
	}

	/**
	 * Callback that renders the block.
	 *
	 * @param $hookName string
	 * @param $args array
	 * @return string
	 */
	function callback($hookName, $args) {
		$params =& $args[0];
		$smarty =& $args[1];
		$output =& $args[2];
		$output .= $this->getContents($smarty, PKPApplication::getRequest());
		return false;
	}

	/*
	 * Private helper methods
	 */
	/**
	 * The application specific context home page hook name.
	 *
	 * @return string
	 */
	function _getContextSpecificHomepageHook() {
		$application =& PKPApplication::getApplication();

		if ($application->getContextDepth() == 0) return null;

		$contextList = $application->getContextList();
		return 'Templates::Index::'.array_shift($contextList);
	}
}
?>
